<?php
header('Content-Type: application/json; charset=utf-8');

require_once 'auth.php';

if (!armsAuthIsAdmin()) {
    http_response_code(404);
    echo json_encode(['sucesso' => false, 'erro' => 'Not found']);
    exit;
}

require_once 'db.php';

try {
    $result = $pdo->query("SELECT COUNT(*) as total FROM arms.request");
    $data = $result->fetch();

    echo json_encode(['sucesso' => true, 'total_pedidos' => (int)$data['total']]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno no diagnóstico.']);
}
?>
