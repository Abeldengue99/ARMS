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
        SELECT a.file_name, a.storage_key
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
