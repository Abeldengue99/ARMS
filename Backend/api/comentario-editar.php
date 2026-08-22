<?php
require_once 'db.php';
require_once 'auth.php';
require_once 'notificacao-servico.php';
header('Content-Type: application/json; charset=utf-8');

armsAuthIniciarSessao();

function armsComentarioBool($valor) {
    return $valor === true || $valor === 1 || $valor === '1' || $valor === 't' || $valor === 'true' || $valor === 'TRUE';
}

if (empty($_SESSION['arms_logado']) || empty($_SESSION['arms_user_id'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Não autenticado.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = trim($data['id'] ?? '');
$body = trim($data['body'] ?? '');
$userId = $_SESSION['arms_user_id'];
$isAdmin = armsComentarioBool($_SESSION['arms_is_admin'] ?? false);

if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Comentário inválido.']);
    exit;
}

if ($body === '') {
    echo json_encode(['sucesso' => false, 'erro' => 'O comentário não pode ficar vazio.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS arms.request_comment_revision (
            id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            comment_id UUID NOT NULL REFERENCES arms.request_comment(id) ON DELETE CASCADE,
            body TEXT NOT NULL,
            revision_number INTEGER NOT NULL,
            edited_by UUID REFERENCES arms.auth_user(id),
            edited_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )
    ");
    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_comment_revision_comment
        ON arms.request_comment_revision (comment_id, edited_at)
    ");

    $stmt = $pdo->prepare("
        SELECT
            rc.id,
            rc.request_id,
            rc.author_id,
            rc.body,
            rc.edit_count
        FROM arms.request_comment rc
        INNER JOIN arms.request r ON r.id = rc.request_id
        WHERE rc.id = :id
        FOR UPDATE
    ");
    $stmt->execute([':id' => $id]);
    $comentario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$comentario) {
        $pdo->rollBack();
        echo json_encode(['sucesso' => false, 'erro' => 'Comentário não encontrado.']);
        exit;
    }

    if (!$isAdmin && strcasecmp((string)$comentario['author_id'], (string)$userId) !== 0) {
        $pdo->rollBack();
        echo json_encode(['sucesso' => false, 'erro' => 'Não tem permissão para editar este comentário.']);
        exit;
    }

    $stmtRevision = $pdo->prepare("
        INSERT INTO arms.request_comment_revision (comment_id, body, revision_number, edited_by)
        VALUES (:comment_id, :body, :revision_number, :edited_by)
    ");
    $stmtRevision->execute([
        ':comment_id' => $id,
        ':body' => $comentario['body'],
        ':revision_number' => ((int)($comentario['edit_count'] ?? 0)) + 1,
        ':edited_by' => $userId
    ]);

    $stmtUpdate = $pdo->prepare("
        UPDATE arms.request_comment
        SET body = :body,
            edited_by = :edited_by,
            edited_at = NOW(),
            edit_count = edit_count + 1
        WHERE id = :id
        RETURNING id, to_char(edited_at, 'YYYY-MM-DD HH24:MI') AS edited_at
    ");
    $stmtUpdate->execute([
        ':body' => $body,
        ':edited_by' => $userId,
        ':id' => $id
    ]);
    $editado = $stmtUpdate->fetch(PDO::FETCH_ASSOC);

    armsNotificarParticipantesPedido(
        $pdo,
        $comentario['request_id'],
        $userId,
        $_SESSION['arms_user_type'] ?? 'AKSANTI',
        $isAdmin,
        'COMMENT',
        [
            'acao' => 'edited',
            'comment_id' => $id,
        ]
    );

    $pdo->commit();

    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Comentário atualizado com sucesso.',
        'comentario' => $editado
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[ARMS] Erro ao editar comentário: ' . $e->getMessage());
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao atualizar o comentário.']);
}
?>
