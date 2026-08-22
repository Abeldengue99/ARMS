<?php
require_once 'db.php';
require_once 'permissoes.php';

armsAuthIniciarSessao();

header('Content-Type: application/json; charset=utf-8');

function armsUtilizadorConvitesBool($valor) {
    return $valor === true || $valor === 1 || $valor === '1' || $valor === 't' || $valor === 'true' || $valor === 'TRUE';
}

if (empty($_SESSION['arms_logado']) || !armsUtilizadorConvitesBool($_SESSION['arms_is_admin'] ?? false)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Apenas Super Admins podem ver histórico de convites.']);
    exit;
}

$id = $_GET['id'] ?? '';
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
    echo json_encode(['sucesso' => false, 'erro' => 'ID de utilizador inválido.']);
    exit;
}

try {
    armsPermissoesGarantirTabelas($pdo);

    $stmt = $pdo->prepare("
        SELECT
            n.id,
            n.type,
            n.channel,
            n.is_read,
            to_char(n.created_at, 'DD/MM/YYYY HH24:MI:SS') as created_at,
            n.payload
        FROM arms.notification n
        WHERE n.recipient_id = :id
          AND n.type IN ('INVITE_RESENT', 'SYSTEM_ERROR')
        ORDER BY n.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([':id' => $id]);
    $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['sucesso' => true, 'dados' => $historico]);
} catch (Exception $e) {
    error_log('[ARMS] Erro ao obter historico de convites: ' . $e->getMessage());
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao processar o pedido.']);
}
?>
