<?php
require_once 'db.php';
require_once 'email.php';
require_once 'utilizador-convite.php';
require_once 'senha-politica.php';

if (session_status() === PHP_SESSION_NONE) {
    $sessionDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'arms-sessions';

    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0777, true);
    }

    if (is_dir($sessionDir) && is_writable($sessionDir)) {
        session_save_path($sessionDir);
    }

    @session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function armsReenviarConviteBool($valor) {
    return $valor === true || $valor === 1 || $valor === '1' || $valor === 't' || $valor === 'true' || $valor === 'TRUE';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
    exit;
}

if (empty($_SESSION['arms_logado']) || !armsReenviarConviteBool($_SESSION['arms_is_admin'] ?? false)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Apenas Super Admins podem reenviar convites.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = trim($data['id'] ?? '');

if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
    echo json_encode(['sucesso' => false, 'erro' => 'ID de utilizador inválido.']);
    exit;
}

try {
    armsSenhaPoliticaGarantirEstrutura($pdo);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.email,
            u.user_type,
            u.is_active,
            u.last_login_at,
            COALESCE(NULLIF(p.full_name, ''), u.email) AS full_name,
            cliente.id AS client_id,
            cliente.name,
            cliente.tax_id,
            cliente.location
        FROM arms.auth_user u
        LEFT JOIN arms.user_profile p ON p.user_id = u.id
        LEFT JOIN LATERAL (
            SELECT c.id, c.name, c.tax_id, c.location
            FROM arms.client_contact cc
            JOIN arms.client c ON c.id = cc.client_id
            WHERE cc.user_id = u.id
            ORDER BY cc.created_at DESC
            LIMIT 1
        ) cliente ON TRUE
        WHERE u.id = :id
        FOR UPDATE OF u
    ");
    $stmt->execute([':id' => $id]);
    $utilizador = $stmt->fetch();

    if (!$utilizador) {
        $pdo->rollBack();
        echo json_encode(['sucesso' => false, 'erro' => 'Utilizador não encontrado.']);
        exit;
    }

    if (!armsReenviarConviteBool($utilizador['is_active'])) {
        $pdo->rollBack();
        echo json_encode(['sucesso' => false, 'erro' => 'Não é possível reenviar convite para uma conta desativada.']);
        exit;
    }

    if (!empty($utilizador['last_login_at'])) {
        $pdo->rollBack();
        echo json_encode(['sucesso' => false, 'erro' => 'Este utilizador já iniciou sessão. Use a recuperação de senha se for necessário redefinir o acesso.']);
        exit;
    }

    if (!filter_var($utilizador['email'], FILTER_VALIDATE_EMAIL)) {
        $pdo->rollBack();
        echo json_encode(['sucesso' => false, 'erro' => 'O e-mail deste utilizador é inválido.']);
        exit;
    }

    $clienteAssociado = null;
    if ($utilizador['user_type'] === 'CLIENT') {
        if (empty($utilizador['client_id'])) {
            $pdo->rollBack();
            echo json_encode(['sucesso' => false, 'erro' => 'Este utilizador cliente não está associado a uma empresa. Atualize o perfil antes de reenviar o convite.']);
            exit;
        }

        $clienteAssociado = [
            'name' => $utilizador['name'],
            'tax_id' => $utilizador['tax_id'],
            'location' => $utilizador['location']
        ];
    }

    $novaSenha = armsGerarSenhaInicial();
    $passwordHash = password_hash($novaSenha, PASSWORD_BCRYPT, ['cost' => 12]);

    if (!password_verify($novaSenha, $passwordHash)) {
        $pdo->rollBack();
        echo json_encode(['sucesso' => false, 'erro' => 'Não foi possível validar a nova senha inicial. Tente novamente.']);
        exit;
    }

    $convite = armsMontarConviteUtilizador(
        $utilizador['full_name'],
        $utilizador['email'],
        $novaSenha,
        $utilizador['user_type'],
        $clienteAssociado
    );

    $stmtUpdate = $pdo->prepare("
        UPDATE arms.auth_user
        SET password_hash = :password_hash,
            password_changed_at = NOW()
        WHERE id = :id
    ");
    $stmtUpdate->execute([
        ':password_hash' => $passwordHash,
        ':id' => $id
    ]);

    try {
        armsEnviarEmail(
            $utilizador['email'],
            $convite['assunto'],
            $convite['titulo'],
            $convite['conteudo_html']
        );
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('[ARMS] Falha ao reenviar convite Brevo: ' . $e->getMessage());
        echo json_encode([
            'sucesso' => false,
            'erro' => armsEmailErroAmigavel($e)
        ]);
        exit;
    }

    $pdo->commit();

    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Convite reenviado com sucesso para ' . $utilizador['email'] . '.',
        'email_enviado' => true,
        'convite' => [
            'assunto' => $convite['assunto'],
            'empresa' => $convite['empresa'],
            'nif' => $convite['nif'],
            'localizacao' => $convite['localizacao']
        ]
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[ARMS] Erro ao reenviar convite: ' . $e->getMessage());
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao reenviar convite.']);
}
