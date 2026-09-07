<?php
require_once 'db.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');

armsAuthIniciarSessao();

if (empty($_SESSION['arms_logado']) || empty($_SESSION['arms_user_id'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Não autenticado.']);
    exit;
}

$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode(['sucesso' => true, 'dados' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT au.id, au.email, COALESCE(up.full_name, au.email) as full_name
        FROM arms.auth_user au
        LEFT JOIN arms.user_profile up ON up.user_id = au.id
        WHERE au.is_active = TRUE
          AND (up.full_name ILIKE :q OR au.email ILIKE :q)
        ORDER BY up.full_name ASC NULLS LAST
        LIMIT 8
    ");
    $stmt->execute([':q' => '%' . $query . '%']);
    $utilizadores = $stmt->fetchAll();

    echo json_encode([
        'sucesso' => true,
        'dados' => $utilizadores
    ]);
} catch (Exception $e) {
    error_log('[ARMS] Erro em mencoes-pesquisar.php: ' . $e->getMessage());
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao pesquisar utilizadores.']);
}
?>
