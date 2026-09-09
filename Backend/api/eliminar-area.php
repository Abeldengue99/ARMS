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

armsExigirPermissao($pdo, 'areas.gerir', 'Não tem permissão para eliminar departamentos.');

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
    echo json_encode(['sucesso' => false, 'erro' => 'ID do departamento não fornecido.']);
    exit;
}

try {
    // Verificar se o departamento tem pedidos associados
    $stmtVerificar = $pdo->prepare("
        SELECT COUNT(*) AS total 
        FROM arms.request 
        WHERE area_id = :id
    ");
    $stmtVerificar->execute(['id' => $id]);
    $resultado = $stmtVerificar->fetch();

    if ($resultado && (int)$resultado['total'] > 0) {
        echo json_encode([
            'sucesso' => false,
            'erro' => 'Este departamento possui ' . $resultado['total'] . ' pedido(s) associado(s). Não é possível eliminá-lo.'
        ]);
        exit;
    }

    // Verificar se tem membros associados
    $stmtMembros = $pdo->prepare("
        SELECT COUNT(*) AS total 
        FROM arms.area_membership 
        WHERE area_id = :id
    ");
    $stmtMembros->execute(['id' => $id]);
    $resultadoMembros = $stmtMembros->fetch();

    if ($resultadoMembros && (int)$resultadoMembros['total'] > 0) {
        // Remover membros primeiro
        $stmtRemoverMembros = $pdo->prepare("DELETE FROM arms.area_membership WHERE area_id = :id");
        $stmtRemoverMembros->execute(['id' => $id]);
    }

    // Eliminar o departamento
    $stmt = $pdo->prepare("DELETE FROM arms.area WHERE id = :id");
    $sucesso = $stmt->execute(['id' => $id]);

    if ($sucesso && $stmt->rowCount() > 0) {
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Departamento eliminado com sucesso!'
        ]);
    } else {
        echo json_encode([
            'sucesso' => false,
            'erro' => 'Departamento não encontrado.'
        ]);
    }

} catch (Exception $e) {
    error_log('[ARMS] Erro ao eliminar departamento: ' . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro interno ao eliminar departamento.'
    ]);
}
?>
