<?php
require_once 'db.php';
require_once 'auth.php';
require_once 'acesso-pedidos.php';

header('Content-Type: application/json; charset=utf-8');

armsAuthExigirLogin();

try {
    [$filtroAcesso, $params] = armsPedidosWhereSql('r', 'pedidos');

    $stmt = $pdo->prepare("
        SELECT
            r.id,
            r.reference as id_str,
            r.title,
            r.status,
            COALESCE(r.destination_type, 'CLIENT') as destination_type,
            r.recipient_user_id,
            to_char(r.created_at, 'DD/MM/YYYY') as date,
            to_char(r.deadline_at, 'DD/MM/YYYY') as deadline,
            r.created_by as created_by_id,
            (r.deadline_at < NOW() AND r.status IN ('SENT', 'RECEIVED')) as deadline_expirado,
            (
                SELECT rr_latest.decision
                FROM arms.request_response rr_latest
                WHERE rr_latest.request_id = r.id
                ORDER BY rr_latest.created_at DESC
                LIMIT 1
            ) as latest_response_decision,
            (
                SELECT COALESCE(au_latest.user_type, '')
                FROM arms.request_response rr_latest
                LEFT JOIN arms.auth_user au_latest ON rr_latest.responded_by = au_latest.id
                WHERE rr_latest.request_id = r.id
                ORDER BY rr_latest.created_at DESC
                LIMIT 1
            ) as latest_response_actor_type,
            a.name as area_name,
            CASE
                WHEN c.name = 'Aksanti' THEN COALESCE(up_creator.full_name, au_creator.email, c.name)
                ELSE c.name
            END as client_name,
            COALESCE(up_creator.full_name, au_creator.email) as recipient_name
        FROM arms.request r
        JOIN arms.area a ON r.area_id = a.id
        JOIN arms.client c ON r.client_id = c.id
        LEFT JOIN arms.auth_user au_creator ON r.created_by = au_creator.id
        LEFT JOIN arms.user_profile up_creator ON r.created_by = up_creator.user_id
        $filtroAcesso
        ORDER BY r.created_at DESC
    ");
    $stmt->execute($params);

    echo json_encode([
        'sucesso' => true,
        'dados' => $stmt->fetchAll()
    ]);
} catch (Exception $e) {
    error_log('[ARMS] Erro ao listar pedidos: ' . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro interno ao carregar pedidos.'
    ]);
}
?>
