<?php
// Endpoint para listar departamentos reais e a contagem de pedidos associados
require_once 'db.php';
require_once 'auth.php';
require_once 'permissoes.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

armsExigirPermissao($pdo, ['areas.ver', 'areas.gerir'], 'Não tem permissão para listar departamentos.');

try {
    $stmt = $pdo->query("
        SELECT 
            a.id,
            a.code,
            a.name,
            (SELECT COUNT(*) FROM arms.request r WHERE r.area_id = a.id) as total_pedidos
        FROM arms.area a
        ORDER BY a.name ASC
    ");
    
    $areas = $stmt->fetchAll();

    echo json_encode([
        'sucesso' => true,
        'dados' => $areas
    ]);
} catch (Exception $e) {
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ]);
}
?>
