<?php
require_once 'db.php';
require_once 'auth.php';
require_once 'acesso-pedidos.php';

header('Content-Type: application/json; charset=utf-8');

armsAuthExigirLogin();

try {
    [$filtroAcesso, $params] = armsPedidosFiltroSql('r', 'dashboard');

    $stmtTotal = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM arms.request r
        WHERE 1=1 $filtroAcesso
    ");
    $stmtTotal->execute($params);
    $totalPedidos = (int)($stmtTotal->fetch()['total'] ?? 0);

    $stmtAbertos = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM arms.request r
        WHERE r.status IN ('DRAFT', 'SENT', 'RECEIVED', 'CLIENT_RESPONDED') $filtroAcesso
    ");
    $stmtAbertos->execute($params);
    $pedidosAbertos = (int)($stmtAbertos->fetch()['total'] ?? 0);

    $stmtVencidos = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM arms.request r
        WHERE r.deadline_at < NOW()
          AND r.status NOT IN ('ACCEPTED', 'REJECTED', 'CLOSED') $filtroAcesso
    ");
    $stmtVencidos->execute($params);
    $pedidosVencidos = (int)($stmtVencidos->fetch()['total'] ?? 0);

    $stmtRespondidos = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM arms.request r
        WHERE r.status NOT IN ('DRAFT', 'SENT') $filtroAcesso
    ");
    $stmtRespondidos->execute($params);
    $respondidos = (int)($stmtRespondidos->fetch()['total'] ?? 0);
    $taxaResposta = $totalPedidos > 0 ? round(($respondidos / $totalPedidos) * 100) : 0;

    $stmtRecentes = $pdo->prepare("
        SELECT
            r.reference,
            r.status,
            CASE
                WHEN COALESCE(r.destination_type, 'CLIENT') = 'AKSANTI' AND r.recipient_user_id IS NOT NULL
                    THEN COALESCE(up_recente.full_name, au_recente.email, c.name)
                ELSE c.name
            END as client_name,
            a.name as area_name,
            r.created_at
        FROM arms.request r
        LEFT JOIN arms.client c ON r.client_id = c.id
        LEFT JOIN arms.area a ON r.area_id = a.id
        LEFT JOIN arms.auth_user au_recente ON r.recipient_user_id = au_recente.id
        LEFT JOIN arms.user_profile up_recente ON r.recipient_user_id = up_recente.user_id
        WHERE 1=1 $filtroAcesso
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmtRecentes->execute($params);
    $recentes = $stmtRecentes->fetchAll() ?? [];

    $stmtPorArea = $pdo->prepare("
        SELECT a.name as area_name, COUNT(r.id) as total
        FROM arms.area a
        LEFT JOIN arms.request r ON a.id = r.area_id
        WHERE 1=1 $filtroAcesso
        GROUP BY a.id, a.name
        ORDER BY total DESC
    ");
    $stmtPorArea->execute($params);
    $porArea = $stmtPorArea->fetchAll() ?? [];

    $stmtPorStatus = $pdo->prepare("
        SELECT r.status, COUNT(r.id) as total
        FROM arms.request r
        WHERE 1=1 $filtroAcesso
        GROUP BY r.status
        ORDER BY total DESC
    ");
    $stmtPorStatus->execute($params);
    $porStatus = $stmtPorStatus->fetchAll() ?? [];

    $topClientes = [];
    if (armsAuthIsAdmin()) {
        $stmtTopClientes = $pdo->prepare("
            SELECT c.name as client_name, COUNT(r.id) as total
            FROM arms.request r
            JOIN arms.client c ON r.client_id = c.id
            WHERE 1=1 $filtroAcesso
            GROUP BY c.id, c.name
            ORDER BY total DESC
            LIMIT 5
        ");
        $stmtTopClientes->execute($params);
        $topClientes = $stmtTopClientes->fetchAll() ?? [];
    }

    $stmtPorMes = $pdo->prepare("
        SELECT
            EXTRACT(MONTH FROM r.created_at) as mes_num,
            TO_CHAR(r.created_at, 'Mon') as mes_nome,
            COUNT(r.id) as total
        FROM arms.request r
        WHERE r.created_at >= NOW() - INTERVAL '6 months' $filtroAcesso
        GROUP BY mes_num, mes_nome
        ORDER BY mes_num ASC
    ");
    $stmtPorMes->execute($params);
    $porMes = $stmtPorMes->fetchAll() ?? [];

    echo json_encode([
        'sucesso' => true,
        'kpis' => [
            'total_pedidos' => $totalPedidos,
            'pedidos_abertos' => $pedidosAbertos,
            'pedidos_vencidos' => $pedidosVencidos,
            'taxa_resposta' => (int)$taxaResposta,
            'sla_medio' => '2.4h'
        ],
        'recentes' => $recentes,
        'por_area' => $porArea,
        'por_status' => $porStatus,
        'top_clientes' => $topClientes,
        'por_mes' => $porMes
    ]);
} catch (Exception $e) {
    error_log('[ARMS] Erro no dashboard-stats: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao carregar estatísticas.']);
}
?>
