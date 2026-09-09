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

armsExigirPermissao($pdo, 'projetos.gerir', 'Não tem permissão para eliminar projetos.');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados inválidos.']);
    exit;
}

$id = $input['id'] ?? null;

if (!$id) {
    echo json_encode(['sucesso' => false, 'erro' => 'ID do projeto não fornecido.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE arms.project 
        SET is_active = FALSE 
        WHERE id = :id
    ");

    $sucesso = $stmt->execute(['id' => $id]);

    if ($sucesso && $stmt->rowCount() > 0) {
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Projeto desativado com sucesso!'
        ]);
    } else {
        echo json_encode([
            'sucesso' => false,
            'erro' => 'Projeto não encontrado ou já desativado.'
        ]);
    }

} catch (Exception $e) {
    error_log('[ARMS] Erro ao desativar projeto: ' . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro interno ao desativar projeto.'
    ]);
}
?>
