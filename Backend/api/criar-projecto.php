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

// Apenas admins gerem projetos, ou ajustar conforme requisitos
armsExigirPermissao($pdo, 'projetos.gerir', 'Não tem permissão para criar projetos.');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados inválidos.']);
    exit;
}

$nome = trim($input['name'] ?? '');
$descricao = trim($input['description'] ?? '');
$clienteId = !empty($input['client_id']) ? $input['client_id'] : null;

// Suporte ao novo formato (array de IDs) e retrocompatibilidade com o formato antigo (single ID)
$ownerUserIds = [];
if (!empty($input['owner_user_ids']) && is_array($input['owner_user_ids'])) {
    $ownerUserIds = array_filter($input['owner_user_ids']);
} elseif (!empty($input['owner_user_id'])) {
    $ownerUserIds = [$input['owner_user_id']];
}

if ($nome === '') {
    echo json_encode(['sucesso' => false, 'erro' => 'O nome do projeto é obrigatório.']);
    exit;
}

if (!$clienteId && empty($ownerUserIds)) {
    echo json_encode(['sucesso' => false, 'erro' => 'O projeto deve estar associado a um cliente ou a pelo menos um membro da equipa.']);
    exit;
}

try {
    // Verificar duplicado
    $stmtDup = $pdo->prepare("SELECT id FROM arms.project WHERE LOWER(BTRIM(name)) = LOWER(:nome) LIMIT 1");
    $stmtDup->execute(['nome' => $nome]);
    if ($stmtDup->fetch()) {
        echo json_encode(['sucesso' => false, 'erro' => 'Já existe um projeto com este nome.']);
        exit;
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO arms.project (name, description, client_id)
        VALUES (:nome, :descricao, :cliente_id)
        RETURNING id, name, description, is_active
    ");

    $stmt->execute([
        'nome' => $nome,
        'descricao' => $descricao,
        'cliente_id' => $clienteId
    ]);

    $novoProjecto = $stmt->fetch();
    $projectoId = $novoProjecto['id'];

    // Inserir membros na tabela de ligação
    if (!empty($ownerUserIds)) {
        $stmtMembro = $pdo->prepare("
            INSERT INTO arms.project_member (project_id, user_id)
            VALUES (:project_id, :user_id)
            ON CONFLICT (project_id, user_id) DO NOTHING
        ");

        foreach ($ownerUserIds as $userId) {
            $stmtMembro->execute([
                'project_id' => $projectoId,
                'user_id' => $userId
            ]);
        }
    }

    $pdo->commit();

    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Projeto criado com sucesso!',
        'projecto' => $novoProjecto
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[ARMS] Erro ao criar projeto: ' . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro interno ao criar projeto.'
    ]);
}
?>
