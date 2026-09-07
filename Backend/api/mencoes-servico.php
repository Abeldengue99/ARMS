<?php
require_once __DIR__ . '/notificacao-servico.php';

/**
 * Extrai UUIDs de utilizadores mencionados num texto no formato @[Nome](uuid)
 */
function armsExtrairMencoes(string $texto): array {
    $mencoes = [];
    if (preg_match_all('/@\[([^\]]+)\]\(([0-9a-f-]{36})\)/i', $texto, $matches)) {
        foreach ($matches[2] as $uuid) {
            $uuidLower = strtolower($uuid);
            $mencoes[$uuidLower] = $uuidLower; // Usa chave para evitar duplicados
        }
    }
    return array_values($mencoes);
}

/**
 * Envia notificação tipo MENTION para todos os utilizadores mencionados,
 * exceto para o autor do texto.
 */
function armsNotificarMencoes(PDO $pdo, string $texto, $requestId, $autorId) {
    $mencionados = armsExtrairMencoes($texto);
    
    if (empty($mencionados)) {
        return 0;
    }

    $stmtReq = $pdo->prepare("SELECT reference FROM arms.request WHERE id = :id");
    $stmtReq->execute([':id' => $requestId]);
    $ref = $stmtReq->fetchColumn();

    $payloadJson = json_encode([
        'pedido_ref' => $ref,
        'message' => 'Você foi mencionado',
        'acao' => 'mention'
    ], JSON_UNESCAPED_UNICODE);

    $stmtInsert = $pdo->prepare("
        INSERT INTO arms.notification (recipient_id, request_id, type, channel, payload)
        VALUES (:recipient_id, :request_id, 'MENTION', 'IN_APP', :payload)
    ");

    $criadas = 0;
    foreach ($mencionados as $destinatarioId) {
        // Não notificar a si próprio
        if (strcasecmp((string)$destinatarioId, (string)$autorId) === 0) {
            continue;
        }

        try {
            $stmtInsert->execute([
                ':recipient_id' => $destinatarioId,
                ':request_id' => $requestId,
                ':payload' => $payloadJson
            ]);
            $criadas += $stmtInsert->rowCount();
        } catch (Exception $e) {
            error_log("[ARMS] Erro ao notificar menção para $destinatarioId: " . $e->getMessage());
        }
    }

    return $criadas;
}
?>
