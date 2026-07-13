<?php
require_once __DIR__ . '/acesso-pedidos.php';

function armsNotificarParticipantesPedido(PDO $pdo, $requestId, $autorId, $autorUserType, $autorIsAdmin, $tipo, array $payload = []) {
    if (function_exists('armsPedidosGarantirDestinoInterno')) {
        armsPedidosGarantirDestinoInterno($pdo);
    }

    $stmtReq = $pdo->prepare("
        SELECT
            id,
            reference,
            area_id,
            client_id,
            created_by,
            COALESCE(destination_type, 'CLIENT') AS destination_type,
            recipient_user_id
        FROM arms.request
        WHERE id = :id
        LIMIT 1
    ");
    $stmtReq->execute([':id' => $requestId]);
    $pedido = $stmtReq->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        return 0;
    }

    $destinatarios = [];
    $adicionar = function ($id) use (&$destinatarios, $autorId) {
        if (!$id || strcasecmp((string)$id, (string)$autorId) === 0) {
            return;
        }

        $destinatarios[strtolower((string)$id)] = $id;
    };

    $destinoInternoAksanti = strtoupper($pedido['destination_type'] ?? 'CLIENT') === 'AKSANTI'
        && !empty($pedido['recipient_user_id']);

    if ($destinoInternoAksanti) {
        $adicionar($pedido['created_by']);
        $adicionar($pedido['recipient_user_id']);
    } elseif ($autorUserType === 'CLIENT' || !$autorIsAdmin) {
        $stmtEquipe = $pdo->prepare("
            SELECT DISTINCT au.id
            FROM arms.auth_user au
            LEFT JOIN arms.area_membership am ON am.user_id = au.id AND am.area_id = :area_id
            WHERE au.is_active = TRUE
              AND au.user_type = 'AKSANTI'
              AND (au.is_admin = TRUE OR am.user_id IS NOT NULL)
        ");
        $stmtEquipe->execute([':area_id' => $pedido['area_id']]);

        foreach ($stmtEquipe->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $adicionar($id);
        }
    }

    if (!$destinoInternoAksanti && $autorIsAdmin) {
        $stmtCliente = $pdo->prepare("
            SELECT DISTINCT cc.user_id
            FROM arms.client_contact cc
            INNER JOIN arms.auth_user au ON au.id = cc.user_id
            WHERE cc.client_id = :client_id
              AND cc.user_id IS NOT NULL
              AND au.is_active = TRUE
        ");
        $stmtCliente->execute([':client_id' => $pedido['client_id']]);

        foreach ($stmtCliente->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $adicionar($id);
        }

        $stmtCriador = $pdo->prepare("
            SELECT id
            FROM arms.auth_user
            WHERE id = :created_by
              AND COALESCE(is_admin, FALSE) = FALSE
              AND is_active = TRUE
            LIMIT 1
        ");
        $stmtCriador->execute([':created_by' => $pedido['created_by']]);
        $adicionar($stmtCriador->fetchColumn());
    }

    if (!$destinatarios) {
        return 0;
    }

    $payload = array_merge([
        'pedido_ref' => $pedido['reference'],
    ], $payload);

    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $stmtInsert = $pdo->prepare("
        INSERT INTO arms.notification (recipient_id, request_id, type, channel, payload)
        VALUES (:recipient_id, :request_id, :type, 'IN_APP', :payload)
    ");

    $criadas = 0;
    foreach ($destinatarios as $destinatarioId) {
        $stmtInsert->execute([
            ':recipient_id' => $destinatarioId,
            ':request_id' => $pedido['id'],
            ':type' => $tipo,
            ':payload' => $payloadJson,
        ]);
        $criadas += $stmtInsert->rowCount();
    }

    return $criadas;
}

?>
