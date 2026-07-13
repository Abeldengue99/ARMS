<?php
// Endpoint para listar utilizadores reais do PostgreSQL
require_once 'db.php';
require_once 'permissoes.php';

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

function armsUtilizadoresBool($valor) {
    return $valor === true || $valor === 1 || $valor === '1' || $valor === 't' || $valor === 'true' || $valor === 'TRUE';
}

if (empty($_SESSION['arms_logado']) || !armsUtilizadoresBool($_SESSION['arms_is_admin'] ?? false)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Apenas Super Admins podem listar utilizadores.']);
    exit;
}

try {
    armsPermissoesGarantirTabelas($pdo);

    $stmt = $pdo->query("
        SELECT
            u.id,
            u.email,
            u.user_type,
            u.is_admin,
            u.is_active,
            to_char(u.last_login_at, 'DD/MM/YYYY HH24:MI') as last_login_at,
            p.full_name,
            p.phone AS cargo,
            cliente.client_id,
            cliente.company_name AS client_company_name,
            cliente.tax_id AS client_tax_id,
            cliente.location AS client_location,
            cliente.primary_email AS client_primary_email,
            COALESCE(permissoes.permissoes, '[]'::json) AS permissoes
        FROM arms.auth_user u
        LEFT JOIN arms.user_profile p ON u.id = p.user_id
        LEFT JOIN LATERAL (
            SELECT
                cc.client_id,
                c.name AS company_name,
                c.tax_id,
                c.location,
                c.primary_email
            FROM arms.client_contact cc
            JOIN arms.client c ON c.id = cc.client_id
            WHERE cc.user_id = u.id
            ORDER BY cc.created_at DESC
            LIMIT 1
        ) cliente ON TRUE
        LEFT JOIN LATERAL (
            SELECT json_agg(up.permission_key ORDER BY up.permission_key) AS permissoes
            FROM arms.user_permission up
            WHERE up.user_id = u.id
        ) permissoes ON TRUE
        ORDER BY u.is_active DESC, p.full_name ASC NULLS LAST, u.email ASC
    ");

    $utilizadores = $stmt->fetchAll();

    foreach ($utilizadores as &$u) {
        $u['full_name'] = $u['full_name'] ?: 'Sem nome';
        $u['cargo'] = $u['cargo'] ?: '';
        $u['phone'] = $u['cargo'];
        $u['first_name'] = $u['full_name'];
        $u['last_name'] = $u['cargo'];
        $u['client_tax_id'] = $u['client_tax_id'] ?: '';
        $u['client_location'] = $u['client_location'] ?: '';
    }

    echo json_encode([
        'sucesso' => true,
        'dados' => $utilizadores
    ]);
} catch (Exception $e) {
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ]);
}
?>
