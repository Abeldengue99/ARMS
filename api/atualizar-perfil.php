<?php
require_once 'db.php';
require_once 'utilizador-identidade.php';

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

function armsBool($valor) {
    return $valor === true || $valor === 1 || $valor === '1' || $valor === 't' || $valor === 'true' || $valor === 'TRUE';
}

function armsTextoTamanho($valor) {
    $texto = (string)$valor;
    return function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen($texto);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
    exit;
}

if (empty($_SESSION['arms_logado']) || empty($_SESSION['arms_user_id'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Não autenticado.']);
    exit;
}

if (!armsBool($_SESSION['arms_is_admin'] ?? false)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Apenas administradores do sistema podem editar dados pessoais.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$fullName = trim($input['full_name'] ?? '');
$email = trim($input['email'] ?? '');
$cargo = trim($input['cargo'] ?? '');
$userId = $_SESSION['arms_user_id'];

if ($fullName === '' || $email === '') {
    echo json_encode(['sucesso' => false, 'erro' => 'Nome completo e e-mail são obrigatórios.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Informe um e-mail válido.']);
    exit;
}

if (armsTextoTamanho($fullName) > 160) {
    echo json_encode(['sucesso' => false, 'erro' => 'O nome completo deve ter no máximo 160 caracteres.']);
    exit;
}

if (armsTextoTamanho($cargo) > 160) {
    echo json_encode(['sucesso' => false, 'erro' => 'O cargo deve ter no máximo 160 caracteres.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmtUser = $pdo->prepare("
        UPDATE arms.auth_user
        SET email = :email
        WHERE id = :id
          AND is_active = TRUE
        RETURNING id, email, user_type, is_admin, is_active
    ");
    $stmtUser->execute([
        ':email' => $email,
        ':id' => $userId
    ]);
    $user = $stmtUser->fetch();

    if (!$user) {
        $pdo->rollBack();
        echo json_encode(['sucesso' => false, 'erro' => 'Utilizador não encontrado ou inativo.']);
        exit;
    }

    $stmtProfile = $pdo->prepare("
        INSERT INTO arms.user_profile (user_id, full_name, phone, locale)
        VALUES (:user_id, :full_name, :cargo, 'pt-PT')
        ON CONFLICT (user_id) DO UPDATE
        SET full_name = EXCLUDED.full_name,
            phone = EXCLUDED.phone
        RETURNING full_name, phone AS cargo, locale
    ");
    $stmtProfile->execute([
        ':user_id' => $userId,
        ':full_name' => $fullName,
        ':cargo' => $cargo !== '' ? $cargo : null
    ]);
    $profile = $stmtProfile->fetch();

    $stmtContacto = $pdo->prepare("
        UPDATE arms.client_contact
        SET full_name = :full_name,
            email = :email
        WHERE user_id = :user_id
    ");
    $stmtContacto->execute([
        ':full_name' => $fullName,
        ':email' => $email,
        ':user_id' => $userId
    ]);

    $stmtCliente = $pdo->prepare("
        SELECT
            cc.client_id,
            c.name AS client_name,
            c.tax_id AS client_tax_id,
            c.location AS client_location,
            c.primary_email AS client_primary_email,
            c.is_active AS client_is_active
        FROM arms.client_contact cc
        JOIN arms.client c ON c.id = cc.client_id
        WHERE cc.user_id = :user_id
        ORDER BY cc.created_at DESC
        LIMIT 1
    ");
    $stmtCliente->execute([':user_id' => $userId]);
    $clienteRow = $stmtCliente->fetch();

    $pdo->commit();

    $_SESSION['arms_user_email'] = $user['email'];
    $_SESSION['arms_full_name'] = $profile['full_name'] ?? $fullName;

    $cliente = null;
    if ($clienteRow) {
        $cliente = [
            'id' => $clienteRow['client_id'],
            'nome' => $clienteRow['client_name'],
            'nif' => $clienteRow['client_tax_id'],
            'localizacao' => $clienteRow['client_location'],
            'email' => $clienteRow['client_primary_email'],
            'ativo' => armsBool($clienteRow['client_is_active'])
        ];
    }

    $nome = $profile['full_name'] ?? $fullName;

    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Dados pessoais atualizados com sucesso.',
        'utilizador' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'nome' => $nome,
            'cargo' => $profile['cargo'] ?? '',
            'tipo' => $user['user_type'],
            'admin' => armsBool($user['is_admin']),
            'ativo' => armsBool($user['is_active']),
            'iniciais' => armsIniciaisUtilizador($nome, $user['email']),
            'locale' => $profile['locale'] ?? 'pt-PT',
            'client_id' => $cliente['id'] ?? null,
            'cliente' => $cliente
        ]
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e->getCode() == '23505') {
        echo json_encode(['sucesso' => false, 'erro' => 'Já existe um utilizador com este e-mail.']);
        exit;
    }

    error_log('[ARMS] Erro ao atualizar perfil: ' . $e->getMessage());
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao atualizar o perfil.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao atualizar o perfil.']);
}
?>
