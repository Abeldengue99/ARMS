<?php
require_once 'db.php';
require_once 'auth.php';
require_once 'configuracoes-servico.php';
require_once 'notificacao-servico.php';
require_once 'arquivo-seguro.php';
header('Content-Type: application/json; charset=utf-8');

armsAuthIniciarSessao();

function armsAnexoBool($valor) {
    return $valor === true || $valor === 1 || $valor === '1' || $valor === 't' || $valor === 'true' || $valor === 'TRUE';
}

if (empty($_SESSION['arms_logado']) || empty($_SESSION['arms_user_id'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Não autenticado.']);
    exit;
}

$anexoId = trim($_POST['id'] ?? '');
$userId = $_SESSION['arms_user_id'];
$isAdmin = armsAnexoBool($_SESSION['arms_is_admin'] ?? false);

if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $anexoId)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Anexo inválido.']);
    exit;
}

if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['sucesso' => false, 'erro' => 'Selecione um ficheiro válido.']);
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

try {
    $pdo->beginTransaction();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS arms.attachment_version (
            id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            attachment_id UUID NOT NULL REFERENCES arms.attachment(id) ON DELETE CASCADE,
            file_name VARCHAR(255) NOT NULL,
            content_type VARCHAR(120),
            size_bytes BIGINT,
            storage_key TEXT NOT NULL,
            replaced_by UUID REFERENCES arms.auth_user(id),
            replaced_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )
    ");
    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_attachment_version_attachment
        ON arms.attachment_version (attachment_id, replaced_at)
    ");

    $stmt = $pdo->prepare("
        SELECT
            a.id,
            a.request_id,
            a.uploaded_by,
            a.file_name,
            a.content_type,
            a.size_bytes,
            a.storage_key
        FROM arms.attachment a
        WHERE a.id = :id
        FOR UPDATE
    ");
    $stmt->execute([':id' => $anexoId]);
    $anexo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$anexo) {
        $pdo->rollBack();
        echo json_encode(['sucesso' => false, 'erro' => 'Anexo não encontrado.']);
        exit;
    }

    if (!$isAdmin && strcasecmp((string)$anexo['uploaded_by'], (string)$userId) !== 0) {
        $pdo->rollBack();
        echo json_encode(['sucesso' => false, 'erro' => 'Não tem permissão para atualizar este anexo.']);
        exit;
    }

    $uploadDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $storageKey = bin2hex(random_bytes(16)) . '.' . $arquivoSeguro['extensao'];
    $filePath = $uploadDir . '/' . $storageKey;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        $pdo->rollBack();
        echo json_encode(['sucesso' => false, 'erro' => 'Não foi possível guardar o novo ficheiro.']);
        exit;
    }

    $stmtVersao = $pdo->prepare("
        INSERT INTO arms.attachment_version (
            attachment_id,
            file_name,
            content_type,
            size_bytes,
            storage_key,
            replaced_by
        )
        VALUES (
            :attachment_id,
            :file_name,
            :content_type,
            :size_bytes,
            :storage_key,
            :replaced_by
        )
    ");
    $stmtVersao->execute([
        ':attachment_id' => $anexoId,
        ':file_name' => $anexo['file_name'],
        ':content_type' => $anexo['content_type'] ?: null,
        ':size_bytes' => $anexo['size_bytes'] ?: null,
        ':storage_key' => $anexo['storage_key'],
        ':replaced_by' => $userId
    ]);

    $stmtUpdate = $pdo->prepare("
        UPDATE arms.attachment
        SET file_name = :file_name,
            content_type = :content_type,
            size_bytes = :size_bytes,
            storage_key = :storage_key,
            updated_by = :updated_by,
            updated_at = NOW(),
            update_count = update_count + 1
        WHERE id = :id
        RETURNING id, file_name, to_char(updated_at, 'YYYY-MM-DD HH24:MI') AS updated_at
    ");
    $stmtUpdate->execute([
        ':file_name' => $arquivoSeguro['nome'],
        ':content_type' => $arquivoSeguro['mime'] ?: null,
        ':size_bytes' => $file['size'],
        ':storage_key' => $storageKey,
        ':updated_by' => $userId,
        ':id' => $anexoId
    ]);
    $atualizado = $stmtUpdate->fetch(PDO::FETCH_ASSOC);

    armsNotificarParticipantesPedido(
        $pdo,
        $anexo['request_id'],
        $userId,
        $_SESSION['arms_user_type'] ?? 'AKSANTI',
        $isAdmin,
        'ATTACHMENT',
        [
            'acao' => 'updated',
            'attachment_id' => $anexoId,
            'file_name' => $arquivoSeguro['nome'],
            'old_file_name' => $anexo['file_name'],
        ]
    );

    $pdo->commit();

    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Ficheiro atualizado com sucesso.',
        'anexo' => $atualizado
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[ARMS] Erro ao atualizar anexo: ' . $e->getMessage());
    echo json_encode(['sucesso' => false, 'erro' => 'Não foi possível atualizar o ficheiro.']);
}
?>
