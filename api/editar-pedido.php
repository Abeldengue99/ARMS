<?php
// Endpoint para editar um pedido existente
require_once 'db.php';
require_once 'auth.php';
require_once 'acesso-pedidos.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    $sessionDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'arms-sessions';

    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0777, true);
    }

    if (is_dir($sessionDir) && is_writable($sessionDir)) {
        session_save_path($sessionDir);
    }

    @session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
    exit;
}

armsAuthExigirLogin();

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['reference'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Referência do pedido não fornecida.']);
    exit;
}

$ref = $input['reference'];
$titulo = trim($input['titulo'] ?? '');
$descricao = trim($input['descricao'] ?? '');
$areaId = $input['area_id'] ?? null;
$clientId = $input['client_id'] ?? null;
$clientEmail = trim($input['client_email'] ?? '');
$deadline = trim($input['deadline'] ?? '');

if (empty($titulo) || empty($descricao)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Título e descrição são obrigatórios.']);
    exit;
}

try {
    // Verificar se o pedido existe e está num estado editável pela Aksanti
    $stmtCheck = $pdo->prepare("SELECT id, status, created_by FROM arms.request WHERE reference = :ref");
    $stmtCheck->execute(['ref' => $ref]);
    $pedido = $stmtCheck->fetch();

    if (!$pedido) {
        echo json_encode(['sucesso' => false, 'erro' => 'Pedido não encontrado.']);
        exit;
    }

    $statusEditaveis = ['DRAFT', 'CLIENT_RESPONDED'];
    if (!in_array($pedido['status'], $statusEditaveis, true)) {
        echo json_encode(['sucesso' => false, 'erro' => 'Este pedido não está num estado editável.']);
        exit;
    }

    $userId = (string)($_SESSION['arms_user_id'] ?? '');
    $isAdmin = armsAuthBool($_SESSION['arms_is_admin'] ?? false);
    $pedidoCriadoPeloUtilizador = strcasecmp((string)$pedido['created_by'], $userId) === 0;

    if (!$isAdmin && !$pedidoCriadoPeloUtilizador) {
        echo json_encode(['sucesso' => false, 'erro' => 'NÃ£o tem permissÃ£o para editar este pedido.']);
        exit;
    }

    // Preparar deadline
    if (!empty($deadline) && strlen($deadline) === 10) {
        $deadline .= ' 23:59:59';
    }

    // Atualizar o pedido
    $sql = "UPDATE arms.request SET title = :titulo, description = :descricao";
    $params = ['titulo' => $titulo, 'descricao' => $descricao, 'pedido_id' => $pedido['id']];

    if ($areaId) {
        $sql .= ", area_id = :area_id";
        $params['area_id'] = $areaId;
    }
    if ($clientId) {
        $sql .= ", client_id = :client_id";
        $params['client_id'] = $clientId;
    }
    if (!empty($clientEmail)) {
        $sql .= ", client_email = :client_email";
        $params['client_email'] = $clientEmail;
    }
    if (!empty($deadline)) {
        $sql .= ", deadline_at = :deadline";
        $params['deadline'] = $deadline;
    }

    $sql .= " WHERE id = :pedido_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Pedido atualizado com sucesso!'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ]);
}
?>

