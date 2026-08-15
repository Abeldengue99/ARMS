<?php
require_once 'db.php';
require_once 'auth.php';
require_once 'acesso-pedidos.php';
require_once 'arquivo-seguro.php';

armsAuthExigirLogin();

if (!isset($_GET['id'])) {
    http_response_code(404);
    die('Arquivo não encontrado');
}

try {
    [$filtroAcesso, $paramsAcesso] = armsPedidosFiltroSql('r', 'download');
    $stmt = $pdo->prepare("
        SELECT a.file_name, a.storage_key, a.uploaded_by, a.request_id, r.reference as request_reference
        FROM arms.attachment a
        JOIN arms.request r ON a.request_id = r.id
        WHERE a.id = :id $filtroAcesso
    ");
    $stmt->execute(array_merge([':id' => $_GET['id']], $paramsAcesso));
    $anexo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$anexo) {
        http_response_code(404);
        die('Arquivo não existe');
    }

    $uploadDir = realpath(__DIR__ . '/../uploads');
    $filePath = realpath(__DIR__ . '/../uploads/' . $anexo['storage_key']);

    if (!$uploadDir || !$filePath || strpos($filePath, $uploadDir . DIRECTORY_SEPARATOR) !== 0 || !file_exists($filePath)) {
        http_response_code(404);
        die('Arquivo não encontrado no servidor');
    }

    $nomeDownload = armsArquivoNomeSeguro($anexo['file_name']);

    if (!empty($anexo['uploaded_by']) && $anexo['uploaded_by'] !== $_SESSION['arms_user_id']) {
        // Prevent duplicate notifications in a short time frame (e.g. 30 minutes)
        $stmtCheck = $pdo->prepare("
            SELECT id FROM arms.notification 
            WHERE recipient_id = ? 
              AND type = 'ATTACHMENT' 
              AND payload::jsonb ->> 'acao' = 'downloaded'
              AND payload::jsonb ->> 'attachment_id' = ?
              AND created_at > (NOW() - INTERVAL '30 minutes')
            LIMIT 1
        ");
        $stmtCheck->execute([$anexo['uploaded_by'], (string)$_GET['id']]);

        if (!$stmtCheck->fetch()) {
            $nomeBaixador = $_SESSION['arms_full_name'] ?? 'Outro utilizador';
            $payload = json_encode([
                'pedido_ref' => $anexo['request_reference'],
                'acao' => 'downloaded',
                'attachment_id' => $_GET['id'],
                'file_name' => $anexo['file_name'],
                'downloaded_by_name' => $nomeBaixador
            ], JSON_UNESCAPED_UNICODE);
            
            $stmtNotif = $pdo->prepare("
                INSERT INTO arms.notification (recipient_id, request_id, type, channel, payload)
                VALUES (?, ?, 'ATTACHMENT', 'IN_APP', ?)
            ");
            $stmtNotif->execute([
                $anexo['uploaded_by'],
                $anexo['request_id'],
                $payload
            ]);
        }
    }

    header('X-Content-Type-Options: nosniff');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . addcslashes($nomeDownload, '\\"') . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
} catch (Exception $e) {
    error_log('[ARMS] Erro no download de anexo: ' . $e->getMessage());
    http_response_code(500);
    die('Erro ao preparar o download.');
}
?>
