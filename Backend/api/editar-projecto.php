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

armsExigirPermissao($pdo, 'projetos.gerir', 'Não tem permissão para editar projetos.');

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
$nome = trim($input['name'] ?? '');
$descricao = trim($input['description'] ?? '');
$clienteId = !empty($input['client_id']) ? $input['client_id'] : null;
$ownerUserId = !empty($input['owner_user_id']) ? $input['owner_user_id'] : null;

if (!$id) {
    echo json_encode(['sucesso' => false, 'erro' => 'ID do projeto não fornecido.']);
    exit;
}

if ($nome === '') {
    echo json_encode(['sucesso' => false, 'erro' => 'O nome do projeto é obrigatório.']);
    exit;
}

if (!$clienteId && !$ownerUserId) {
    echo json_encode(['sucesso' => false, 'erro' => 'O projeto deve estar associado a um cliente ou a um membro da equipa.']);
    exit;
}

try {
    // Verificar duplicado
    $stmtDup = $pdo->prepare("SELECT id FROM arms.project WHERE LOWER(BTRIM(name)) = LOWER(:nome) AND id != :id LIMIT 1");
    $stmtDup->execute(['nome' => $nome, 'id' => $id]);
    if ($stmtDup->fetch()) {
        echo json_encode(['sucesso' => false, 'erro' => 'Já existe outro projeto com este nome.']);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE arms.project 
        SET name = :nome, description = :descricao, client_id = :cliente_id, owner_user_id = :owner_user_id 
        WHERE id = :id
    ");

    $sucesso = $stmt->execute([
        'nome' => $nome,
        'descricao' => $descricao,
        'cliente_id' => $clienteId,
        'owner_user_id' => $ownerUserId,
        'id' => $id
    ]);

    if ($sucesso && $stmt->rowCount() > 0) {
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Projeto atualizado com sucesso!'
        ]);
    } else {
        echo json_encode([
            'sucesso' => false,
            'erro' => 'Projeto não encontrado ou nenhuma alteração efetuada.'
        ]);
    }

} catch (Exception $e) {
    error_log('[ARMS] Erro ao editar projeto: ' . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro interno ao editar projeto.'
    ]);
}
?>
