<?php
// Endpoint para criar um novo pedido no PostgreSQL
require_once 'db.php';
require_once 'auth.php';
require_once 'acesso-pedidos.php';

header('Content-Type: application/json; charset=utf-8');

armsAuthIniciarSessao();

// Apenas aceitar pedidos POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
    exit;
}

// Verificar se utilizador está logado
if (empty($_SESSION['arms_logado']) || empty($_SESSION['arms_user_id'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Não autenticado.']);
    exit;
}

// Ler os dados JSON do corpo do pedido
$input = json_decode(file_get_contents('php://input'), true);
$userType = $_SESSION['arms_user_type'] ?? 'AKSANTI';
$isAdmin = armsAuthBool($_SESSION['arms_is_admin'] ?? false);
$destinationType = strtoupper(trim($input['destination_type'] ?? 'CLIENT'));
if (!in_array($destinationType, ['CLIENT', 'AKSANTI'], true)) {
    $destinationType = 'CLIENT';
}
$recipientScope = strtoupper(trim($input['recipient_scope'] ?? 'DEPARTMENT'));
if (!in_array($recipientScope, ['USER', 'DEPARTMENT'], true)) {
    $recipientScope = 'DEPARTMENT';
}

if ($userType === 'CLIENT' || !$isAdmin) {
    $destinationType = 'AKSANTI';
    $recipientScope = 'DEPARTMENT';
}

// Validar campos obrigatórios
$campos = ['titulo', 'descricao', 'area_id', 'deadline'];
if ($userType !== 'CLIENT' && $isAdmin && $destinationType === 'CLIENT') {
    $campos[] = 'client_id';
    $campos[] = 'client_email';
} elseif ($userType !== 'CLIENT' && $isAdmin && $destinationType === 'AKSANTI' && $recipientScope === 'USER') {
    $campos[] = 'recipient_user_id';
}
foreach ($campos as $campo) {
    if (empty($input[$campo])) {
        echo json_encode(['sucesso' => false, 'erro' => "O campo '$campo' é obrigatório."]);
        exit;
    }
}

ob_start();
try {
    armsPedidosGarantirDestinoInterno($pdo);

    $createdBy = $_SESSION['arms_user_id'];
    $clientId = $input['client_id'] ?? null;
    $clientEmail = $input['client_email'] ?? null;
    $recipientUserId = null;

    if ($userType === 'CLIENT') {
        if (empty($_SESSION['arms_client_id'])) {
            ob_end_clean();
            echo json_encode(['sucesso' => false, 'erro' => 'Esta conta de cliente não está associada a uma empresa.']);
            exit;
        }

        $stmtClienteSessao = $pdo->prepare("
            SELECT id, primary_email
            FROM arms.client
            WHERE id = :id
              AND is_active = TRUE
        ");
        $stmtClienteSessao->execute([':id' => $_SESSION['arms_client_id']]);
        $clienteSessao = $stmtClienteSessao->fetch();

        if (!$clienteSessao) {
            ob_end_clean();
            echo json_encode(['sucesso' => false, 'erro' => 'Empresa associada não encontrada ou inativa.']);
            exit;
        }

        $clientId = $clienteSessao['id'];
        $clientEmail = $_SESSION['arms_user_email'] ?? $clienteSessao['primary_email'];
    } elseif (!$isAdmin) {
        $clienteInterno = armsPedidosClienteInternoAksanti($pdo);
        if (!$clienteInterno) {
            ob_end_clean();
            echo json_encode(['sucesso' => false, 'erro' => 'Não foi possível preparar o destino interno Aksanti.']);
            exit;
        }

        $clientId = $clienteInterno['id'];
        $clientEmail = $clienteInterno['primary_email'];
    } elseif ($destinationType === 'AKSANTI') {
        $recipientUserId = trim($input['recipient_user_id'] ?? '');

        if ($recipientScope === 'USER' && $recipientUserId === (string)$createdBy) {
            ob_end_clean();
            echo json_encode(['sucesso' => false, 'erro' => 'Escolha outro membro Aksanti como destinatÃ¡rio.']);
            exit;
        }

        if ($recipientScope !== 'USER') {
            $recipientUserId = null;
            $destinatario = null;
        } else {
            $stmtDestinatario = $pdo->prepare("
            SELECT au.id, au.email, COALESCE(up.full_name, au.email) AS full_name
            FROM arms.auth_user au
            LEFT JOIN arms.user_profile up ON up.user_id = au.id
            WHERE au.id = :id
              AND au.user_type = 'AKSANTI'
              AND au.is_active = TRUE
            LIMIT 1
        ");
        $stmtDestinatario->execute([':id' => $recipientUserId]);
        $destinatario = $stmtDestinatario->fetch(PDO::FETCH_ASSOC);

        if (!$destinatario) {
            ob_end_clean();
            echo json_encode(['sucesso' => false, 'erro' => 'DestinatÃ¡rio Aksanti nÃ£o encontrado ou inativo.']);
            exit;
        }

        }

        $clienteInterno = armsPedidosClienteInternoAksanti($pdo);
        if (!$clienteInterno) {
            ob_end_clean();
            echo json_encode(['sucesso' => false, 'erro' => 'NÃ£o foi possÃ­vel preparar o destino interno Aksanti.']);
            exit;
        }

        $clientId = $clienteInterno['id'];
        $clientEmail = $destinatario ? $destinatario['email'] : $clienteInterno['primary_email'];
    }
    
    // Se o prazo vier apenas como data (YYYY-MM-DD), adicionar a hora 23:59:59
    // para garantir que o limite é o final do dia, passando na verificação (deadline_at >= created_at)
    $deadline = $input['deadline'];
    if (strlen($deadline) === 10) {
        $deadline .= ' 23:59:59';
    }

    $pdo->beginTransaction();

    // Inserir o pedido na tabela request (entra sempre como DRAFT inicialmente)
    $stmt = $pdo->prepare("
        INSERT INTO arms.request (title, description, area_id, client_id, client_email, created_by, deadline_at, destination_type, recipient_user_id, status)
        VALUES (:titulo, :descricao, :area_id, :client_id, :client_email, :created_by, :deadline, :destination_type, :recipient_user_id, 'DRAFT')
        RETURNING id, reference, status, to_char(created_at, 'DD/MM/YYYY') as date
    ");

    $stmt->execute([
        'titulo'       => $input['titulo'],
        'descricao'    => $input['descricao'],
        'area_id'      => $input['area_id'],
        'client_id'    => $clientId,
        'client_email' => $clientEmail,
        'created_by'   => $createdBy,
        'deadline'     => $deadline,
        'destination_type' => $destinationType,
        'recipient_user_id' => $recipientUserId
    ]);

    $novoPedido = $stmt->fetch();

    $areaIds = [];
    if (!empty($input['area_ids']) && is_array($input['area_ids'])) {
        $areaIds = $input['area_ids'];
    }

    armsPedidosRegistrarDestinatarios(
        $pdo,
        $novoPedido['id'],
        $destinationType,
        $clientId,
        $input['area_id'],
        $createdBy,
        $recipientUserId,
        $areaIds
    );

    /*
        $stmtNotificar = $pdo->prepare("
            INSERT INTO arms.notification (recipient_id, request_id, type, channel, payload)
            SELECT DISTINCT au.id, :request_id::uuid, 'NEW_REQUEST', 'IN_APP',
                jsonb_build_object(
                    'pedido_ref', :reference::text,
                    'message', 'Novo pedido interno para análise'
                )
            FROM arms.auth_user au
            LEFT JOIN arms.area_membership am ON am.user_id = au.id
            WHERE au.is_active = TRUE
              AND au.user_type = 'AKSANTI'
              AND au.id <> :created_by::uuid
              AND (au.is_admin = TRUE OR am.area_id = :area_id::uuid)
        ");
        $stmtNotificar->execute([
            ':request_id' => $novoPedido['id'],
            ':reference' => $novoPedido['reference'],
            ':created_by' => $createdBy,
            ':area_id' => $input['area_id']
        ]);

    */

    $pdo->commit();

    $lixo = ob_get_clean();
    if (!empty(trim($lixo))) {
        error_log('[ARMS] criar-pedido.php lixo no buffer: ' . $lixo);
    }

    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Pedido guardado como rascunho. Reveja os dados e envie quando estiver tudo em ordem.',
        'pedido' => $novoPedido
    ]);

} catch (Exception $e) {
    ob_end_clean();
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ]);
}
?>
