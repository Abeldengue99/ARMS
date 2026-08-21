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
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

require_once 'db.php';
require_once 'auth.php';
require_once 'permissoes.php';

// Validação de sessão / permissões
armsExigirPermissao($pdo, ['areas.ver', 'areas.gerir'], 'Não tem permissão para listar departamentos.');

try {
    $stmt = $pdo->query("
        SELECT 
            a.id,
            a.code,
            a.name,
            a.is_restricted,
            (SELECT COUNT(*) FROM arms.request r WHERE r.area_id = a.id) as total_pedidos
        FROM arms.area a
        ORDER BY a.name ASC
    ");
    
    $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'sucesso' => true,
        'dados' => $areas
    ]);

} catch (Exception $e) {
    error_log('[ARMS] Erro ao listar departamentos: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro interno ao carregar departamentos.'
    ]);
}
?>
