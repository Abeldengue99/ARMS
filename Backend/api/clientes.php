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
require_once 'auth.php'; // Se tiver o seu sistema de validação de sessão

// Opcional: Garantir que o utilizador está autenticado antes de devolver dados
// armsAuthIniciarSessao();
// if (empty($_SESSION['arms_user_id'])) {
//     http_response_code(401);
//     echo json_encode(['sucesso' => false, 'erro' => 'Não autenticado.']);
//     exit;
// }

try {
    // Consulta SQL adaptada para ir buscar os dados da tabela arms.client 
    // e mapear com os nomes de colunas que o frontend (clientes.js) espera.
    $stmt = $pdo->query("
        SELECT 
            c.id,
            c.name AS company_name,
            c.primary_email AS contact_email,
            c.is_active,
            CASE WHEN c.is_active = TRUE THEN 'ACTIVE' ELSE 'INACTIVE' END AS status,
            NULL AS tax_id,
            NULL AS location,
            NULL AS contact_name,
            c.created_at
        FROM arms.client c
        ORDER BY c.name ASC
    ");
    
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'sucesso' => true,
        'dados' => $clientes
    ]);

} catch (Exception $e) {
    error_log('[ARMS] Erro ao carregar clientes: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro interno ao carregar clientes.'
    ]);
}
?>
