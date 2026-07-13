<?php
/**
 * ARMS - API de recuperação de palavra-passe.
 * Gera uma senha temporária e envia as instruções por e-mail.
 */
require_once 'db.php';
require_once 'email.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (empty($email)) {
    echo json_encode(['sucesso' => false, 'erro' => 'E-mail é obrigatório.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Informe um e-mail válido.']);
    exit;
}

function armsGerarSenhaTemporaria($tamanho = 10) {
    $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $senha = '';
    $limite = strlen($alfabeto) - 1;

    for ($i = 0; $i < $tamanho; $i++) {
        $senha .= $alfabeto[random_int(0, $limite)];
    }

    return $senha;
}

function montarEmailRecuperacaoSenha($email, $senhaTemporaria) {
    $emailSeguro = armsEmailEscapar($email);
    $senhaSegura = armsEmailEscapar($senhaTemporaria);

    return <<<HTML
<p style="margin:0 0 16px;">Recebemos um pedido para recuperar o acesso à sua conta no ARMS.</p>
<p style="margin:0 0 18px;">Use os dados abaixo para iniciar sessão e altere a senha logo depois.</p>
<div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:10px; padding:18px; margin:22px 0;">
    <p style="margin:0 0 8px;"><strong>E-mail de acesso:</strong> {$emailSeguro}</p>
    <p style="margin:0;"><strong>Senha temporária:</strong> {$senhaSegura}</p>
</div>
<p style="margin:18px 0 0;">Se não pediu esta recuperação, contacte a equipa responsável pelo ARMS.</p>
HTML;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, email
        FROM arms.auth_user
        WHERE email = :email
          AND is_active = true
    ");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Se este e-mail estiver registado, receberá instruções de recuperação em breve.'
        ]);
        exit;
    }

    $novaSenha = armsGerarSenhaTemporaria();
    $hash = password_hash($novaSenha, PASSWORD_BCRYPT, ['cost' => 12]);

    $pdo->beginTransaction();

    $stmtUpdate = $pdo->prepare("
        UPDATE arms.auth_user
        SET password_hash = :hash
        WHERE id = :id
    ");
    $stmtUpdate->execute([
        ':hash' => $hash,
        ':id' => $user['id']
    ]);

    try {
        armsEnviarEmail(
            $user['email'],
            'Recuperação de palavra-passe no ARMS',
            'Recuperação de palavra-passe',
            montarEmailRecuperacaoSenha($user['email'], $novaSenha)
        );
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('[ARMS] Falha ao enviar recuperação de senha: ' . $e->getMessage());
        echo json_encode([
            'sucesso' => false,
            'erro' => armsEmailErroAmigavel($e)
        ]);
        exit;
    }

    $pdo->commit();

    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Instruções de recuperação enviadas para ' . $email . '. Verifique a sua caixa de entrada.'
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    error_log('[ARMS] Erro na recuperação de senha: ' . $e->getMessage());
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao processar a recuperação de senha.']);
}
?>
