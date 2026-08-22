<?php
/**
 * ARMS — API de Resposta Formal do Cliente
 * Permite ao cliente aceitar, rejeitar ou responder oficialmente a um pedido,
 * gravando na tabela request_response e atualizando o status do pedido.
 */
require_once 'db.php';
require_once 'auth.php';
require_once 'acesso-pedidos.php';
header('Content-Type: application/json; charset=utf-8');

armsAuthExigirLogin();

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['reference']) || empty($data['decisao'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados incompletos. Referência e decisão são obrigatórias.']);
    exit;
}

$ref = trim($data['reference']);
$decisao = trim($data['decisao']); // ACCEPTED, REJECTED, CLIENT_RESPONDED
$mensagem = trim($data['mensagem'] ?? '');
$userId = $_SESSION['arms_user_id'];
$userType = $_SESSION['arms_user_type'] ?? 'AKSANTI';

// Apenas clientes ou admins em nome do cliente deveriam responder oficialmente
// Mas vamos permitir que AKSANTI também responda se necessário, porém o foco é CLIENT
// A tabela real só permite: PENDING, ACCEPTED, REJECTED
$allowedStatuses = ['ACCEPTED', 'REJECTED', 'PENDING'];
if (!in_array($decisao, $allowedStatuses)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Decisão inválida.']);
    exit;
}

$mensagensDecisao = [
    'ACCEPTED' => 'Pedido aceite com sucesso.',
    'REJECTED' => 'Pedido rejeitado com sucesso.',
    'PENDING' => 'Alteração solicitada com sucesso.'
];

try {
    armsPedidosGarantirDestinoInterno($pdo);

    $pdo->beginTransaction();

    // 1. Obter o ID do Pedido
    $stmt = $pdo->prepare("
        SELECT
            r.id,
            r.reference,
            r.status,
            r.client_id,
            r.created_by,
            COALESCE(r.destination_type, 'CLIENT') AS destination_type,
            r.recipient_user_id,
            COALESCE(au_created.is_admin, FALSE) AS created_by_is_admin,
            cc.user_id AS client_user_id
        FROM arms.request r
        LEFT JOIN arms.auth_user au_created ON r.created_by = au_created.id
        LEFT JOIN arms.client_contact cc ON r.client_id = cc.client_id
        WHERE r.reference = ?
        ORDER BY cc.user_id IS NULL, cc.created_at ASC
        LIMIT 1
    ");
    $stmt->execute([$ref]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        throw new Exception("Pedido não encontrado.");
    }

    $reqId = $req['id'];

    $destinoInternoAksanti = strtoupper($req['destination_type'] ?? 'CLIENT') === 'AKSANTI'
        && !empty($req['recipient_user_id']);
    $utilizadorEDestinatarioInterno = $destinoInternoAksanti
        && strcasecmp((string)$req['recipient_user_id'], (string)$userId) === 0;
    $adminAtual = armsAuthBool($_SESSION['arms_is_admin'] ?? false);

    // Validar se o cliente atual é dono do pedido (se for cliente)
    if ($userType === 'CLIENT') {
        $clientIdDaSessao = $_SESSION['arms_client_id'] ?? null;
        if ($req['client_id'] !== $clientIdDaSessao || (string)$req['created_by'] === (string)$userId) {
            throw new Exception("Não tem permissão para responder a este pedido.");
        }
    } elseif ($utilizadorEDestinatarioInterno) {
        if (strcasecmp((string)$req['created_by'], (string)$userId) === 0) {
            throw new Exception("Não tem permissão para responder a este pedido.");
        }
    } elseif (!$adminAtual) {
        throw new Exception("Apenas Super Admins podem responder formalmente por esta área.");
    } elseif ((string)$req['created_by'] === (string)$userId || armsAuthBool($req['created_by_is_admin'] ?? false)) {
        throw new Exception("Este pedido não aguarda resposta administrativa.");
    }

    // 2. Inserir a resposta oficial na tabela request_response
    //    Colunas reais: body, decision, decided_by, decided_at (schema exige decided_by/at quando != PENDING)
    $sqlResp = "INSERT INTO arms.request_response (request_id, responded_by, body, decision, decided_by, decided_at)
                VALUES (?, ?, ?, ?, ?, NOW()) RETURNING id";
    $stmtResp = $pdo->prepare($sqlResp);
    $stmtResp->execute([$reqId, $userId, $mensagem, $decisao, $userId]);
    
    // 3. Atualizar o estado do pedido na tabela request
    //    Mapear decisão para status do pedido conforme o state machine:
    //    ACCEPTED -> ACCEPTED, REJECTED -> REJECTED
    $novoStatus = $decisao; // ACCEPTED ou REJECTED mapeiam directamente
    if ($decisao === 'PENDING') {
        $novoStatus = 'CLIENT_RESPONDED'; // PENDING na resposta = o cliente respondeu mas sem decisão final
    }
    // Definir o utilizador atual para o trigger do audit log capturar automaticamente
    $pdo->exec("SET LOCAL arms.current_user_id = " . $pdo->quote($userId));

    $sqlUpdate = "UPDATE arms.request SET status = ? WHERE id = ?";
    $stmtUpdate = $pdo->prepare($sqlUpdate);

    if (!in_array($req['status'], ['SENT', 'RECEIVED', 'CLIENT_RESPONDED'], true)) {
        throw new Exception("Este pedido não está num estado que permita resposta formal.");
    }

    if ($req['status'] === 'SENT') {
        $stmtUpdate->execute(['RECEIVED', $reqId]);
        $req['status'] = 'RECEIVED';
    }

    // O State Machine da Base de Dados exige que o pedido vá para CLIENT_RESPONDED
    // antes de ir para ACCEPTED ou REJECTED.
    if ($req['status'] === 'RECEIVED' && $novoStatus !== 'CLIENT_RESPONDED') {
        $stmtUpdate->execute(['CLIENT_RESPONDED', $reqId]);
        $req['status'] = 'CLIENT_RESPONDED';
    }

    // Finalmente, atualiza para o status final (ACCEPTED ou REJECTED ou PENDING/CLIENT_RESPONDED)
    if ($req['status'] !== $novoStatus) {
        $stmtUpdate->execute([$novoStatus, $reqId]);
    }

    // 4. Criar notificação para a outra parte
    $destinatarioId = ($userType === 'CLIENT') ? $req['created_by'] : $req['created_by'];
    if ($destinatarioId) {
        $sqlNotif = "INSERT INTO arms.notification (recipient_id, request_id, type, channel, payload) VALUES (?, ?, 'RESPONSE', 'IN_APP', ?)";
        $payload = json_encode([
            'pedido_ref' => $req['reference'],
            'decision' => $decisao,
            'message' => $mensagem
        ]);
        $stmtNotif = $pdo->prepare($sqlNotif);
        $stmtNotif->execute([$destinatarioId, $reqId, $payload]);
    }

    // O trigger trg_request_status_audit cria automaticamente o request_audit_log

    $pdo->commit();

    echo json_encode([
        'sucesso' => true, 
        'mensagem' => $mensagensDecisao[$decisao] ?? 'Decisão registada com sucesso.',
        'novo_status' => $decisao
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[ARMS] Erro ao responder pedido: ' . $e->getMessage());
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao registar a decisão.']);
}
