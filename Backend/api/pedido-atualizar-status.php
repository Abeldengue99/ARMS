<?php
require_once 'db.php';
require_once 'auth.php';
require_once 'acesso-pedidos.php';
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    $sessionDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'arms-sessions';

    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0777, true);
    }

    if (is_dir($sessionDir) && is_writable($sessionDir)) {
        session_save_path($sessionDir);
    }

    @session_start();
}

if (empty($_SESSION['arms_logado']) || empty($_SESSION['arms_user_id'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Não autenticado.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['reference']) || empty($data['novo_status'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados incompletos.']);
    exit;
}

$reference = trim($data['reference']);
$novoStatus = trim($data['novo_status']);
$userId = $_SESSION['arms_user_id'];
$userType = $_SESSION['arms_user_type'] ?? 'AKSANTI';
$clientId = $_SESSION['arms_client_id'] ?? null;

try {
    armsPedidosGarantirDestinoInterno($pdo);
    armsPedidosGarantirTransicoes($pdo);

    $pdo->beginTransaction();
    $pdo->exec("SET LOCAL arms.current_user_id = " . $pdo->quote($userId));

    $stmtReq = $pdo->prepare("
        SELECT
            r.id,
            r.reference,
            r.status,
            r.area_id,
            r.client_id,
            r.created_by,
            COALESCE(r.destination_type, 'CLIENT') AS destination_type,
            r.recipient_user_id,
            cc.user_id as client_user_id,
            r.title,
            r.description,
            to_char(r.deadline_at, 'YYYY-MM-DD HH24:MI:SS') AS deadline_at_iso,
            up_creator.full_name as created_by_name
        FROM arms.request r
        LEFT JOIN arms.client_contact cc ON r.client_id = cc.client_id
        LEFT JOIN arms.user_profile up_creator ON r.created_by = up_creator.user_id
        WHERE r.reference = ?
        ORDER BY cc.user_id IS NULL, cc.created_at ASC
        LIMIT 1
    ");
    $stmtReq->execute([$reference]);
    $reqData = $stmtReq->fetch();

    if (!$reqData) {
        throw new Exception('Pedido não encontrado.');
    }

    $destinoInternoAksanti = strtoupper($reqData['destination_type'] ?? 'CLIENT') === 'AKSANTI'
        && !empty($reqData['recipient_user_id']);
    $utilizadorEDestinatarioInterno = $destinoInternoAksanti
        && strcasecmp((string)$reqData['recipient_user_id'], (string)$userId) === 0;
    $adminAtual = armsAuthBool($_SESSION['arms_is_admin'] ?? false);
    $pedidoCriadoPeloUtilizador = strcasecmp((string)$reqData['created_by'], (string)$userId) === 0;
    $criadorPodeEnviar = $pedidoCriadoPeloUtilizador
        && $novoStatus === 'SENT'
        && in_array($reqData['status'], ['DRAFT', 'CLIENT_RESPONDED'], true);

    if ($criadorPodeEnviar) {
        // O criador do pedido pode enviar o próprio rascunho, seja admin, cliente ou colaborador.
    } elseif ($userType === 'CLIENT') {
        if (
            $novoStatus !== 'RECEIVED'
            || $reqData['status'] !== 'SENT'
            || (string)$reqData['client_id'] !== (string)$clientId
            || (string)$reqData['created_by'] === (string)$userId
        ) {
            throw new Exception('Não tem permissão para atualizar este pedido.');
        }
    } elseif ($utilizadorEDestinatarioInterno) {
        if (
            $novoStatus !== 'RECEIVED'
            || $reqData['status'] !== 'SENT'
            || strcasecmp((string)$reqData['created_by'], (string)$userId) === 0
        ) {
            throw new Exception('Nao tem permissao para atualizar este pedido.');
        }
    } elseif (!$adminAtual) {
        throw new Exception('Apenas Super Admins podem atualizar o estado deste pedido.');
    }

    if ($userType !== 'CLIENT' && !$utilizadorEDestinatarioInterno && !in_array($novoStatus, ['SENT', 'DRAFT', 'CLOSED'], true)) {
        throw new Exception('Estado não permitido para atualização manual.');
    }

    if ($userType !== 'CLIENT' && !$utilizadorEDestinatarioInterno && $novoStatus === 'SENT' && !in_array($reqData['status'], ['DRAFT', 'CLIENT_RESPONDED'], true)) {
        throw new Exception('Apenas pedidos em rascunho ou com alteração solicitada podem ser enviados.');
    }

    $stmt = $pdo->prepare("UPDATE arms.request SET status = ? WHERE id = ?");
    $stmt->execute([$novoStatus, $reqData['id']]);

    $payload = json_encode([
        'pedido_ref' => $reference,
        'from_status' => $reqData['status'],
        'to_status' => $novoStatus,
        'created_by_name' => $reqData['created_by_name'] ?? 'Alguém'
    ]);

    if ($novoStatus === 'SENT') {
        if ($destinoInternoAksanti && !empty($reqData['recipient_user_id'])) {
            if (strcasecmp((string)$reqData['recipient_user_id'], (string)$userId) !== 0) {
                $stmtNotif = $pdo->prepare("
                    INSERT INTO arms.notification (recipient_id, request_id, type, channel, payload)
                    VALUES (?, ?, 'NEW_REQUEST', 'IN_APP', ?)
                ");
                $stmtNotif->execute([$reqData['recipient_user_id'], $reqData['id'], $payload]);
            }
        } elseif (strtoupper((string)$reqData['destination_type']) === 'AKSANTI') {
            $stmtNotif = $pdo->prepare("
                INSERT INTO arms.notification (recipient_id, request_id, type, channel, payload)
                SELECT DISTINCT au.id, CAST(:request_id AS uuid), 'NEW_REQUEST', 'IN_APP', CAST(:payload AS jsonb)
                FROM arms.auth_user au
                LEFT JOIN arms.area_membership am ON am.user_id = au.id
                WHERE au.is_active = TRUE
                  AND au.user_type = 'AKSANTI'
                  AND au.id <> CAST(:created_by AS uuid)
                  AND (au.is_admin = TRUE OR am.area_id = CAST(:area_id AS uuid))
            ");
            $stmtNotif->execute([
                ':request_id' => $reqData['id'],
                ':payload' => $payload,
                ':created_by' => $userId,
                ':area_id' => $reqData['area_id']
            ]);
        } elseif (!empty($reqData['client_user_id']) && strcasecmp((string)$reqData['client_user_id'], (string)$userId) !== 0) {
            $stmtNotif = $pdo->prepare("
                INSERT INTO arms.notification (recipient_id, request_id, type, channel, payload)
                VALUES (?, ?, 'NEW_REQUEST', 'IN_APP', ?)
            ");
            $stmtNotif->execute([$reqData['client_user_id'], $reqData['id'], $payload]);
        }
    } else {
        if ($userType === 'CLIENT' || $utilizadorEDestinatarioInterno) {
            $destinatarioId = $reqData['created_by'];
        } elseif ($destinoInternoAksanti) {
            $destinatarioId = $reqData['recipient_user_id'];
        } else {
            $destinatarioId = $reqData['client_user_id'];
        }

        if ($destinatarioId && strcasecmp((string)$destinatarioId, (string)$userId) !== 0) {
            $stmtNotif = $pdo->prepare("
                INSERT INTO arms.notification (recipient_id, request_id, type, channel, payload)
                VALUES (?, ?, 'STATUS_CHANGE', 'IN_APP', ?)
            ");
            $stmtNotif->execute([$destinatarioId, $reqData['id'], $payload]);
        }
    }

    $pdo->commit();

    // Enviar a resposta imediatamente para o cliente para evitar demoras na interface
    $response = json_encode(['sucesso' => true, 'novo_status' => $novoStatus]);
    
    ignore_user_abort(true);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header("Connection: close");
    ob_start();
    echo $response;
    $size = ob_get_length();
    header("Content-Length: $size");
    ob_end_flush();
    @ob_flush();
    flush();
    if (session_id()) session_write_close();

    // Envio de emails de notificação para pedidos foi desativado a pedido do utilizador.
    // Os utilizadores recebem apenas emails de acesso e recuperação de senha.
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[ARMS] Erro ao atualizar estado do pedido: ' . $e->getMessage());
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao atualizar o pedido.']);
}
