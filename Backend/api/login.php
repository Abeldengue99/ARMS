<?php
// --- CABEÇALHOS CORS OBRIGATÓRIOS ---
header("Access-Control-Allow-Origin: https://arms.support");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
// ------------------------------------

/**
 * ARMS - API de Autenticação (Login)
 * Valida credenciais contra a tabela auth_user do PostgreSQL.
 */
require_once 'db.php';
require_once 'auth.php';
require_once 'utilizador-identidade.php';
require_once 'senha-util.php';
require_once 'senha-politica.php';
require_once 'permissoes.php';
require_once 'seguranca-servico.php';

armsAuthIniciarSessao();

header('Content-Type: application/json; charset=utf-8');

function armsApiBool($valor) {
    return $valor === true || $valor === 1 || $valor === '1' || $valor === 't' || $valor === 'true' || $valor === 'TRUE';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$email = trim($input['email'] ?? '');
$senha = armsSenhaNormalizarEntrada($input['senha'] ?? '');

if (empty($email) || empty($senha)) {
    echo json_encode(['sucesso' => false, 'erro' => 'E-mail e senha são obrigatórios.']);
    exit;
}

try {
    armsSenhaPoliticaGarantirEstrutura($pdo);

    $bloqueio = armsSegurancaBloqueioAtual($pdo, $email);
    if ($bloqueio) {
        armsSegurancaRegistarEvento($pdo, $email, null, 'LOGIN_BLOCKED', false, 'Bloqueio temporário ativo.');
        echo json_encode([
            'sucesso' => false,
            'erro' => 'Conta temporariamente bloqueada por tentativas falhadas. Tente novamente em cerca de ' . (int)$bloqueio['minutos_restantes'] . ' minuto(s).'
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT
            au.id,
            au.email,
            au.password_hash,
            au.password_changed_at,
            au.user_type,
            au.is_admin,
            au.is_active,
            up.full_name,
            up.phone AS cargo,
            up.locale,
            up.avatar_url,
            cliente.client_id,
            cliente.client_name,
            cliente.client_tax_id,
            cliente.client_location,
            cliente.client_primary_email,
            cliente.client_is_active
        FROM arms.auth_user au
        LEFT JOIN arms.user_profile up ON au.id = up.user_id
        LEFT JOIN LATERAL (
            SELECT
                cc.client_id,
                c.name AS client_name,
                c.tax_id AS client_tax_id,
                c.location AS client_location,
                c.primary_email AS client_primary_email,
                c.is_active AS client_is_active
            FROM arms.client_contact cc
            JOIN arms.client c ON c.id = cc.client_id
            WHERE cc.user_id = au.id
            ORDER BY cc.created_at DESC
            LIMIT 1
        ) cliente ON TRUE
        WHERE au.email = :email
    ");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        armsSegurancaRegistarFalha($pdo, $email, null, 'Credenciais inválidas.');
        echo json_encode(['sucesso' => false, 'erro' => 'Credenciais inválidas.']);
        exit;
    }

    if (!armsApiBool($user['is_active'])) {
        armsSegurancaRegistarFalha($pdo, $email, $user['id'], 'Conta desativada.');
        echo json_encode(['sucesso' => false, 'erro' => 'Esta conta foi desativada. Contacte o administrador.']);
        exit;
    }

    if (!password_verify($senha, $user['password_hash'])) {
        armsSegurancaRegistarFalha($pdo, $email, $user['id'], 'Senha incorreta.');
        echo json_encode(['sucesso' => false, 'erro' => 'Credenciais inválidas.']);
        exit;
    }

    session_regenerate_id(true);

    $stmtLogin = $pdo->prepare("UPDATE arms.auth_user SET last_login_at = NOW() WHERE id = :id");
    $stmtLogin->execute(['id' => $user['id']]);

    $_SESSION['arms_user_id'] = $user['id'];
    $_SESSION['arms_user_email'] = $user['email'];
    $_SESSION['arms_user_type'] = $user['user_type'];
    $_SESSION['arms_is_admin'] = armsApiBool($user['is_admin']);
    $_SESSION['arms_full_name'] = $user['full_name'] ?? 'Utilizador';
    $_SESSION['arms_client_id'] = $user['client_id'] ?? null;
    $_SESSION['arms_client_name'] = $user['client_name'] ?? null;
    $_SESSION['arms_logado'] = true;
    armsSegurancaRegistarLoginSucesso($pdo, $user['id'], $user['email']);

    $nome = $user['full_name'] ?? $user['email'];
    $iniciais = armsIniciaisUtilizador($nome, $user['email']);
    $permissoes = armsPermissoesDoUtilizador($pdo, $user['id'], armsApiBool($user['is_admin']));
    $senhaPolitica = armsSenhaPoliticaDados($user['password_changed_at']);
    $_SESSION['arms_password_expired'] = $senhaPolitica['password_expired'];

    $cliente = null;
    if (!empty($user['client_id'])) {
        $cliente = [
            'id' => $user['client_id'],
            'nome' => $user['client_name'],
            'nif' => $user['client_tax_id'],
            'localizacao' => $user['client_location'],
            'email' => $user['client_primary_email'],
            'ativo' => armsApiBool($user['client_is_active'])
        ];
    }

    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Sessão iniciada com sucesso!',
        'utilizador' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'nome' => $user['full_name'] ?? 'Utilizador',
            'cargo' => $user['cargo'] ?? '',
            'tipo' => $user['user_type'],
            'admin' => armsApiBool($user['is_admin']),
            'permissoes' => $permissoes,
            'ativo' => armsApiBool($user['is_active']),
            'iniciais' => $iniciais,
            'locale' => $user['locale'] ?? 'pt-PT',
            'client_id' => $user['client_id'] ?? null,
            'senha_expirada' => $senhaPolitica['password_expired'],
            'password_expired' => $senhaPolitica['password_expired'],
            'password_changed_at' => $senhaPolitica['password_changed_at'],
            'password_expires_at' => $senhaPolitica['password_expires_at'],
            'password_days_remaining' => $senhaPolitica['password_days_remaining'],
            'cliente' => $cliente
        ]
    ]);

} catch (Exception $e) {
    error_log('[ARMS] Erro no login: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao iniciar sessão.']);
}
?>
