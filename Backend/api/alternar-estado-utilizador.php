<?php
require_once 'db.php';
require_once 'auth.php';

armsAuthIniciarSessao();

header('Content-Type: application/json; charset=utf-8');

function armsAlternarEstadoBool($valor) {
    return $valor === true || $valor === 1 || $valor === '1' || $valor === 't' || $valor === 'true' || $valor === 'TRUE';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido']);
    exit;
}

if (empty($_SESSION['arms_logado']) || !armsAlternarEstadoBool($_SESSION['arms_is_admin'] ?? false)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Apenas Super Admins podem ativar ou desativar utilizadores.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = trim($data['id'] ?? '');
$ativo = array_key_exists('ativo', $data ?? []) ? filter_var($data['ativo'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : false;

if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
    echo json_encode(['sucesso' => false, 'erro' => 'ID de utilizador inválido.']);
    exit;
}

if (!empty($_SESSION['arms_user_id']) && strcasecmp((string) $_SESSION['arms_user_id'], $id) === 0 && !$ativo) {
    echo json_encode(['sucesso' => false, 'erro' => 'Não podes desativar a tua própria conta enquanto tens sessão iniciada.']);
    exit;
}

try {
    if (!$ativo) {
        $stmtAtual = $pdo->prepare("
            SELECT is_admin, user_type, is_active
            FROM arms.auth_user
            WHERE id = :id
        ");
        $stmtAtual->execute([':id' => $id]);
        $utilizadorAtual = $stmtAtual->fetch();

        if (
            $utilizadorAtual &&
            armsAlternarEstadoBool($utilizadorAtual['is_admin']) &&
            $utilizadorAtual['user_type'] === 'AKSANTI' &&
            armsAlternarEstadoBool($utilizadorAtual['is_active'])
        ) {
            $stmtOutrosGestores = $pdo->prepare("
                SELECT COUNT(*)
                FROM arms.auth_user
                WHERE user_type = 'AKSANTI'
                  AND is_admin = TRUE
                  AND is_active = TRUE
                  AND id <> :id
            ");
            $stmtOutrosGestores->execute([':id' => $id]);

            if ((int)$stmtOutrosGestores->fetchColumn() < 1) {
                echo json_encode(['sucesso' => false, 'erro' => 'Não é possível desativar o último Super Admin ativo do sistema.']);
                exit;
            }
        }
    }

    $stmt = $pdo->prepare("
        UPDATE arms.auth_user
        SET is_active = :ativo
        WHERE id = :id
        RETURNING id
    ");
    $stmt->execute([
        ':ativo' => $ativo ? 'true' : 'false',
        ':id' => $id
    ]);

    if (!$stmt->fetchColumn()) {
        echo json_encode(['sucesso' => false, 'erro' => 'Utilizador não encontrado.']);
        exit;
    }

    echo json_encode([
        'sucesso' => true,
        'mensagem' => $ativo ? 'Utilizador ativado com sucesso.' : 'Utilizador desativado com sucesso.'
    ]);
} catch (PDOException $e) {
    error_log('[ARMS] Erro ao alternar estado do utilizador: ' . $e->getMessage());
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao atualizar o estado do utilizador.']);
}
?>
