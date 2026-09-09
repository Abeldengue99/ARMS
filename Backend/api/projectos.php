<?php
// --- CABEÇALHOS CORS E SESSÃO OBRIGATÓRIOS ---
header("Access-Control-Allow-Origin: https://arms.support");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

require_once 'db.php';
require_once 'auth.php';
require_once 'permissoes.php';

try {
    $stmt = $pdo->query("
        SELECT 
            p.id,
            p.name,
            p.description,
            p.client_id,
            c.name AS client_name,
            p.owner_user_id,
            u.full_name AS owner_name,
            p.is_active,
            CASE WHEN p.is_active = TRUE THEN 'ACTIVE' ELSE 'INACTIVE' END AS status,
            p.created_at
        FROM arms.project p
        LEFT JOIN arms.client c ON p.client_id = c.id
        LEFT JOIN arms.user_account u ON p.owner_user_id = u.id
        ORDER BY p.name ASC
    ");
    
    $projectos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'sucesso' => true,
        'dados' => $projectos
    ]);

} catch (Exception $e) {
    error_log('[ARMS] Erro ao carregar projetos: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro interno ao carregar projetos.'
    ]);
}
?>
