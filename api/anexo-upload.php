<?php
require_once 'db.php';
require_once 'auth.php';
require_once 'acesso-pedidos.php';
require_once 'configuracoes-servico.php';
require_once 'notificacao-servico.php';
require_once 'arquivo-seguro.php';
header('Content-Type: application/json; charset=utf-8');

armsAuthIniciarSessao();

if (empty($_SESSION['arms_logado']) || empty($_SESSION['arms_user_id'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Não autenticado.']);
    exit;
}

if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['sucesso' => false, 'erro' => 'Erro no arquivo']);
    exit;
}

if (!isset($_POST['reference'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados incompletos']);
    exit;
}

$authorId = $_SESSION['arms_user_id'];
$authorType = $_SESSION['arms_user_type'] ?? 'AKSANTI';
$authorIsAdmin = armsAuthBool($_SESSION['arms_is_admin'] ?? false);

try {
    [$filtroAcesso, $paramsAcesso] = armsPedidosFiltroSql('r', 'anexo');
    $stmt = $pdo->prepare("SELECT r.id FROM arms.request r WHERE r.reference = :ref $filtroAcesso");
    $stmt->execute(array_merge([':ref' => $_POST['reference']], $paramsAcesso));
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        echo json_encode(['sucesso' => false, 'erro' => 'Pedido não encontrado']);
        exit;
    }

    $file = $_FILES['arquivo'];
    [$uploadValido, $erroUpload, $arquivoSeguro] = armsArquivoValidarUpload($file);

    if (!$uploadValido) {
        echo json_encode(['sucesso' => false, 'erro' => $erroUpload]);
        exit;
    }

    $maxMb = armsConfiguracaoInteiro($pdo, 'attachment_max_size_mb', 50, 1, 10240);
    $maxBytes = $maxMb * 1024 * 1024;

    if ($file['size'] > $maxBytes) {
        echo json_encode(['sucesso' => false, 'erro' => 'O ficheiro não pode ter mais de ' . $maxMb . 'MB.']);
        exit;
    }

    $upload_dir = __DIR__ . '/../uploads';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $storage_key = bin2hex(random_bytes(16)) . '.' . $arquivoSeguro['extensao'];
    $file_path = $upload_dir . '/' . $storage_key;

    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        echo json_encode(['sucesso' => false, 'erro' => 'Erro ao salvar']);
        exit;
    }

    $sql = "INSERT INTO arms.attachment (request_id, file_name, content_type, size_bytes, storage_key, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?)
            RETURNING id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$req['id'], $arquivoSeguro['nome'], $arquivoSeguro['mime'] ?: null, $file['size'], $storage_key, $authorId]);
    $anexoId = $stmt->fetchColumn();

    armsNotificarParticipantesPedido($pdo, $req['id'], $authorId, $authorType, $authorIsAdmin, 'ATTACHMENT', [
        'acao' => 'uploaded',
        'attachment_id' => $anexoId,
        'file_name' => $arquivoSeguro['nome'],
    ]);

    echo json_encode(['sucesso' => true, 'file_name' => $arquivoSeguro['nome']]);

} catch (Exception $e) {
    error_log('[ARMS] Erro no upload de anexo: ' . $e->getMessage());
    echo json_encode(['sucesso' => false, 'erro' => 'Não foi possível guardar o anexo.']);
}
?>
