<?php
require_once 'db.php';
require_once 'auth.php';
require_once 'senha-util.php';
require_once 'senha-politica.php';

header('Content-Type: application/json; charset=utf-8');

armsAuthExigirLogin();

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    $data = [];
}

$senhaAtual = armsSenhaNormalizarEntrada($data['senha_atual'] ?? '');
$novaSenha = armsSenhaNormalizarEntrada($data['nova_senha'] ?? '');
$confirmarSenha = array_key_exists('confirmar_senha', $data ?? [])
    ? armsSenhaNormalizarEntrada($data['confirmar_senha'])
    : $novaSenha;

if ($senhaAtual === '' || $novaSenha === '') {
    echo json_encode(['sucesso' => false, 'erro' => 'Informe a senha atual e a nova senha.']);
    exit;
}

if (strlen($novaSenha) < 6) {
    echo json_encode(['sucesso' => false, 'erro' => 'A nova senha deve ter pelo menos 6 caracteres.']);
    exit;
}

if ($novaSenha !== $confirmarSenha) {
    echo json_encode(['sucesso' => false, 'erro' => 'A nova senha e a confirmação não coincidem.']);
    exit;
}

try {
    $userId = $_SESSION['arms_user_id'];
    armsSenhaPoliticaGarantirEstrutura($pdo);

    $stmt = $pdo->prepare("
        SELECT id, password_hash, is_active
        FROM arms.auth_user
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $userId]);
    $utilizador = $stmt->fetch();

    if (!$utilizador || !armsAuthBool($utilizador['is_active'] ?? false)) {
        echo json_encode(['sucesso' => false, 'erro' => 'Sessão inválida. Inicie sessão novamente.']);
        exit;
    }

    if (!password_verify($senhaAtual, $utilizador['password_hash'])) {
        echo json_encode(['sucesso' => false, 'erro' => 'A senha atual está incorreta.']);
        exit;
    }

    $novoHash = password_hash($novaSenha, PASSWORD_BCRYPT, ['cost' => 12]);

    $stmtUpdate = $pdo->prepare("
        UPDATE arms.auth_user
        SET password_hash = :password_hash,
            password_changed_at = NOW()
        WHERE id = :id
    ");
    $stmtUpdate->execute([
        ':password_hash' => $novoHash,
        ':id' => $userId,
    ]);

    $_SESSION['arms_password_expired'] = false;

    echo json_encode(['sucesso' => true, 'mensagem' => 'Senha alterada com sucesso.', 'password_expired' => false]);
} catch (Exception $e) {
    http_response_code(500);
    error_log('[ARMS] Erro ao alterar senha: ' . $e->getMessage());
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao alterar a senha.']);
}
?>
