<?php
// Endpoint POST para criar um novo departamento/área no PostgreSQL
require_once 'db.php';
require_once 'auth.php';
require_once 'permissoes.php';

header('Content-Type: application/json; charset=utf-8');
armsExigirPermissao($pdo, 'areas.gerir', 'Não tem permissão para criar departamentos.');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['nome']) || empty($input['codigo'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Nome e Código são obrigatórios.']);
    exit;
}

// O código na BD deve ser uppercase sem espaços
$codigoUpper = strtoupper(trim($input['codigo']));
$codigoUpper = preg_replace('/\s+/', '_', $codigoUpper);

try {
    $stmt = $pdo->prepare("
        INSERT INTO arms.area (code, name, is_restricted)
        VALUES (:code, :name, :is_restricted)
        RETURNING id, code, name
    ");

    $stmt->execute([
        'code'          => $codigoUpper,
        'name'          => trim($input['nome']),
        'is_restricted' => !empty($input['restrito']) && $input['restrito'] == true ? 'true' : 'false'
    ]);

    $novaArea = $stmt->fetch();

    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Departamento criado com sucesso!',
        'area' => $novaArea
    ]);

} catch (Exception $e) {
    // Verificar se o código já existe
    if (strpos($e->getMessage(), 'area_code_key') !== false) {
        echo json_encode(['sucesso' => false, 'erro' => 'Este Código já existe no sistema.']);
    } else {
        error_log('[ARMS] Erro ao criar departamento: ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao criar o departamento.']);
    }
}
?>
