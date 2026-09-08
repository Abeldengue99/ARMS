<?php
require_once 'db.php';
require_once 'auth.php';

armsAuthIniciarSessao();

header('Content-Type: application/json; charset=utf-8');

function armsEliminarEstadoBool(mixed $valor) {
    return $valor === true || $valor === 1 || $valor === '1' || $valor === 't' || $valor === 'true' || $valor === 'TRUE';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido']);
    exit;
}

if (empty($_SESSION['arms_logado']) || !armsEliminarEstadoBool($_SESSION['arms_is_admin'] ?? false)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Apenas Super Admins podem eliminar utilizadores.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = trim($data['id'] ?? '');

if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
    echo json_encode(['sucesso' => false, 'erro' => 'ID de utilizador inválido.']);
    exit;
}

if (!empty($_SESSION['arms_user_id']) && strcasecmp((string) $_SESSION['arms_user_id'], $id) === 0) {
    echo json_encode(['sucesso' => false, 'erro' => 'Não podes eliminar a tua própria conta enquanto tens sessão iniciada.']);
    exit;
}

try {
    // Verificar se é o último Super Admin ativo antes de eliminar
    $stmtAtual = $pdo->prepare("
        SELECT is_admin, user_type, is_active
        FROM arms.auth_user
        WHERE id = :id
    ");
    $stmtAtual->execute([':id' => $id]);
    $utilizadorAtual = $stmtAtual->fetch();

    if (!$utilizadorAtual) {
        echo json_encode(['sucesso' => false, 'erro' => 'Utilizador não encontrado.']);
        exit;
    }

    if (
        armsEliminarEstadoBool($utilizadorAtual['is_admin']) &&
        $utilizadorAtual['user_type'] === 'AKSANTI' &&
        armsEliminarEstadoBool($utilizadorAtual['is_active'])
    ) {
        $stmtOutrosGestores = $pdo->prepare("
            SELECT COUNT(*)
            FROM arms.auth_user
            WHERE user_type = 'AKSANTI'
              AND is_admin = TRUE
              AND is_active = TRUE
              AND id <> :id
        ");
        $stmtOutrosGestores->execute([':id' => $id]);

        if ((int)$stmtOutrosGestores->fetchColumn() < 1) {
            echo json_encode(['sucesso' => false, 'erro' => 'Não é possível eliminar o último Super Admin ativo do sistema.']);
            exit;
        }
    }

    // Tentar eliminar — o PostgreSQL bloqueia automaticamente via Foreign Keys
    // se o utilizador tiver pedidos, respostas, comentários ou histórico associado
    $stmt = $pdo->prepare("
        DELETE FROM arms.auth_user
        WHERE id = :id
        RETURNING id
    ");
    $stmt->execute([':id' => $id]);

    if (!$stmt->fetchColumn()) {
        echo json_encode(['sucesso' => false, 'erro' => 'Utilizador não encontrado.']);
        exit;
    }

    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Utilizador eliminado com sucesso. Pode voltar a criar uma conta com o mesmo e-mail.'
    ]);
} catch (PDOException $e) {
    error_log('[ARMS] Erro ao eliminar utilizador: ' . $e->getMessage());

    // SQLSTATE 23503 = foreign_key_violation (utilizador tem histórico associado)
    if ($e->getCode() === '23503') {
        echo json_encode([
            'sucesso' => false,
            'erro' => 'Este utilizador tem pedidos ou histórico associado e não pode ser eliminado para garantir a integridade dos dados. Utilize a opção de desativar.'
        ]);
        exit;
    }

    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao eliminar o utilizador.']);
}
?>
