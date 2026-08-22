<?php
require_once 'db.php';
require_once 'auth.php';
require_once 'acesso-pedidos.php';
require_once 'notificacao-servico.php';
header('Content-Type: application/json; charset=utf-8');

armsAuthIniciarSessao();

if (empty($_SESSION['arms_logado']) || empty($_SESSION['arms_user_id'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Não autenticado.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['reference']) || !isset($data['body'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados incompletos']);
    exit;
}

$authorId = $_SESSION['arms_user_id'];
$authorType = $_SESSION['arms_user_type'] ?? 'AKSANTI';
$authorIsAdmin = armsAuthBool($_SESSION['arms_is_admin'] ?? false);

try {
    [$filtroAcesso, $paramsAcesso] = armsPedidosFiltroSql('r', 'comentario');
    $stmt = $pdo->prepare("SELECT r.id FROM arms.request r WHERE r.reference = :ref $filtroAcesso");
    $stmt->execute(array_merge([':ref' => $data['reference']], $paramsAcesso));
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        echo json_encode(['sucesso' => false, 'erro' => 'Pedido não encontrado']);
        exit;
    }

    $sql = "INSERT INTO arms.request_comment (request_id, author_id, body, visibility)
            VALUES (?, ?, ?, 'BOTH') RETURNING id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$req['id'], $authorId, $data['body']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    armsNotificarParticipantesPedido($pdo, $req['id'], $authorId, $authorType, $authorIsAdmin, 'COMMENT', [
        'acao' => 'created',
        'comment_id' => $result['id'],
    ]);

    echo json_encode(['sucesso' => true, 'id' => $result['id']]);

} catch (Exception $e) {
    error_log('[ARMS] Erro ao criar comentário: ' . $e->getMessage());
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao guardar o comentário.']);
}
?>
