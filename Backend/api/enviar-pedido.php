<?php
// Endpoint para enviar um pedido (muda o status de DRAFT para SENT)
require_once 'db.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
    exit;
}

armsAuthExigirAdmin();

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['id'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'ID do pedido não fornecido.']);
    exit;
}

$id = $input['id'];

try {
    // Verificar o status atual
    $stmtCheck = $pdo->prepare("SELECT status FROM arms.request WHERE reference = :ref");
    $stmtCheck->execute(['ref' => $id]);
    $statusAtual = $stmtCheck->fetchColumn();

    if (!$statusAtual) {
        echo json_encode(['sucesso' => false, 'erro' => 'Pedido não encontrado.']);
        exit;
    }

    if ($statusAtual !== 'DRAFT') {
        echo json_encode(['sucesso' => false, 'erro' => 'Apenas pedidos em rascunho podem ser enviados.']);
        exit;
    }

    // Atualizar status para SENT
    $stmtUpdate = $pdo->prepare("
        UPDATE arms.request 
        SET status = 'SENT'
        WHERE reference = :ref
    ");
    $stmtUpdate->execute(['ref' => $id]);

    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Pedido enviado com sucesso!'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ]);
}
?>
