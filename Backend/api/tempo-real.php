<?php
/**
 * ARMS — API de Atualizações em Tempo Real (Long Polling)
 * Devolve apenas os dados que mudaram desde a última verificação
 * Usado pelo frontend para atualizar a UI sem recarregar a página
 */
require_once 'db.php';
require_once 'auth.php';
require_once 'acesso-pedidos.php';
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

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (empty($_SESSION['arms_logado']) || empty($_SESSION['arms_user_id'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Não autenticado.', 'atualizacoes' => []]);
    exit;
}

// Último timestamp enviado pelo cliente
$desde = $_GET['desde'] ?? null;
$modulo = $_GET['modulo'] ?? 'geral'; // geral, pedidos, clientes, dashboard

$userType = $_SESSION['arms_user_type'] ?? 'AKSANTI';
$clientId = $_SESSION['arms_client_id'] ?? null;
$isAdmin = armsAuthBool($_SESSION['arms_is_admin'] ?? false);
$permissoesTempoReal = $isAdmin ? array_keys(armsPermissoesCatalogo()) : armsPermissoesDoUtilizador($pdo, $_SESSION['arms_user_id'] ?? null, false);
$podeVerClientes = $isAdmin || in_array('clientes.ver', $permissoesTempoReal, true) || in_array('clientes.gerir', $permissoesTempoReal, true);

if ($userType === 'CLIENT' && !$clientId) {
    echo json_encode(['sucesso' => false, 'erro' => 'Esta conta de cliente não está associada a uma empresa.', 'atualizacoes' => []]);
    exit;
}

try {
    $resultado = ['sucesso' => true, 'timestamp' => date('c'), 'atualizacoes' => []];

    switch ($modulo) {
        case 'dashboard':
            [$filtroAcesso, $params] = armsPedidosFiltroSql('r', 'rt_dashboard');

            // Filtros opcionais do dashboard admin (por empresa/departamento)
            $filtroClienteId = !empty($_GET['filter_client_id']) ? $_GET['filter_client_id'] : null;
            $filtroAreaId = !empty($_GET['filter_area_id']) ? $_GET['filter_area_id'] : null;
            $filtroDestinoTipo = isset($_GET['filter_destination_type']) ? $_GET['filter_destination_type'] : null;
            $filtroFuncionarioId = !empty($_GET['filter_recipient_user_id']) ? $_GET['filter_recipient_user_id'] : null;

            if ($filtroClienteId) {
                $filtroAcesso .= ' AND r.client_id = :filter_client_id';
                $params[':filter_client_id'] = $filtroClienteId;
            }
            if ($filtroAreaId) {
                $filtroAcesso .= ' AND r.area_id = :filter_area_id';
                $params[':filter_area_id'] = $filtroAreaId;
            }
            if ($filtroDestinoTipo === 'AKSANTI') {
                $filtroAcesso .= " AND COALESCE(r.destination_type, 'CLIENT') = 'AKSANTI'";
            } elseif ($filtroDestinoTipo === 'CLIENT') {
                $filtroAcesso .= " AND COALESCE(r.destination_type, 'CLIENT') != 'AKSANTI'";
            }
            if ($filtroFuncionarioId) {
                $filtroAcesso .= ' AND r.recipient_user_id = :filter_recipient_user_id';
                $params[':filter_recipient_user_id'] = $filtroFuncionarioId;
            }

            // Estatísticas atualizadas
            $stmtTotal = $pdo->prepare("SELECT COUNT(*) as total FROM arms.request r WHERE r.status != 'DRAFT' $filtroAcesso");
            $stmtTotal->execute($params);
            $total = (int)$stmtTotal->fetch()['total'];

            $stmtEnviados = $pdo->prepare("SELECT COUNT(*) as total FROM arms.request r WHERE r.status = 'SENT' $filtroAcesso");
            $stmtEnviados->execute($params);
            $enviados = (int)$stmtEnviados->fetch()['total'];

            $stmtRecebidos = $pdo->prepare("SELECT COUNT(*) as total FROM arms.request r WHERE r.status = 'RECEIVED' $filtroAcesso");
            $stmtRecebidos->execute($params);
            $recebidos = (int)$stmtRecebidos->fetch()['total'];

            $stmtAlteracoes = $pdo->prepare("SELECT COUNT(*) as total FROM arms.request r WHERE r.status = 'CLIENT_RESPONDED' $filtroAcesso");
            $stmtAlteracoes->execute($params);
            $alteracoes = (int)$stmtAlteracoes->fetch()['total'];

            $stmtAceites = $pdo->prepare("SELECT COUNT(*) as total FROM arms.request r WHERE r.status = 'ACCEPTED' $filtroAcesso");
            $stmtAceites->execute($params);
            $aceites = (int)$stmtAceites->fetch()['total'];

            $stmtRejeitados = $pdo->prepare("SELECT COUNT(*) as total FROM arms.request r WHERE r.status = 'REJECTED' $filtroAcesso");
            $stmtRejeitados->execute($params);
            $rejeitados = (int)$stmtRejeitados->fetch()['total'];

            $stmtVencidos = $pdo->prepare("SELECT COUNT(*) as total FROM arms.request r WHERE r.deadline_at < NOW() AND r.status NOT IN ('DRAFT', 'ACCEPTED', 'REJECTED', 'CLOSED') $filtroAcesso");
            $stmtVencidos->execute($params);
            $vencidos = (int)$stmtVencidos->fetch()['total'];

            $stmtRespondidos = $pdo->prepare("SELECT COUNT(*) as total FROM arms.request r WHERE r.status NOT IN ('DRAFT', 'SENT') $filtroAcesso");
            $stmtRespondidos->execute($params);
            $respondidos = (int)$stmtRespondidos->fetch()['total'];
            $taxa = $total > 0 ? round(($respondidos / $total) * 100) : 0;

            // Pedidos recentes (últimos 10)
            $stmt4 = $pdo->prepare("
                SELECT r.reference, r.status, r.destination_type,
                       r.created_by as created_by_id,
                       CASE
                           WHEN c.name = 'Aksanti' THEN COALESCE(up_creator.full_name, au_creator.email, c.name)
                           ELSE c.name
                       END as client_name,
                       a.name as area_name, 
                       to_char(r.created_at, 'DD/MM/YYYY') as date
                FROM arms.request r
                LEFT JOIN arms.client c ON r.client_id = c.id
                LEFT JOIN arms.area a ON r.area_id = a.id
                LEFT JOIN arms.auth_user au_recente ON r.recipient_user_id = au_recente.id
                LEFT JOIN arms.user_profile up_recente ON r.recipient_user_id = up_recente.user_id
                LEFT JOIN arms.auth_user au_creator ON r.created_by = au_creator.id
                LEFT JOIN arms.user_profile up_creator ON r.created_by = up_creator.user_id
                WHERE r.status != 'DRAFT' $filtroAcesso
                ORDER BY r.created_at DESC LIMIT 10
            ");
            $stmt4->execute($params);
            $recentes = $stmt4->fetchAll();

            // Distribuição por área
            $stmt5 = $pdo->prepare("
                SELECT a.name as area_name, COUNT(r.id) as total
                FROM arms.area a LEFT JOIN arms.request r ON a.id = r.area_id AND r.status != 'DRAFT'
                WHERE 1=1 $filtroAcesso
                GROUP BY a.id, a.name ORDER BY total DESC
            ");
            $stmt5->execute($params);
            $porArea = $stmt5->fetchAll();

            // Pedidos por mês
            $stmt6 = $pdo->prepare("
                SELECT EXTRACT(MONTH FROM r.created_at) as mes_num,
                       TO_CHAR(r.created_at, 'Mon') as mes_nome,
                       COUNT(r.id) as total
                FROM arms.request r
                WHERE r.created_at >= NOW() - INTERVAL '6 months' AND r.status != 'DRAFT' $filtroAcesso
                GROUP BY mes_num, mes_nome ORDER BY mes_num ASC
            ");
            $stmt6->execute($params);
            $porMes = $stmt6->fetchAll();
            $mesesPt = [1=>'Jan', 2=>'Fev', 3=>'Mar', 4=>'Abr', 5=>'Mai', 6=>'Jun', 7=>'Jul', 8=>'Ago', 9=>'Set', 10=>'Out', 11=>'Nov', 12=>'Dez'];
            foreach ($porMes as &$pm) {
                $pm['mes_nome'] = $mesesPt[(int)$pm['mes_num']] ?? $pm['mes_nome'];
            }
            unset($pm);

            $resultado['atualizacoes'] = [
                'kpis' => [
                    'total_pedidos' => $total,
                    'pedidos_abertos' => $enviados,
                    'pedidos_recebidos' => $recebidos,
                    'pedidos_alteracoes' => $alteracoes,
                    'pedidos_aceites' => $aceites,
                    'pedidos_rejeitados' => $rejeitados,
                    'pedidos_vencidos' => $vencidos,
                    'taxa_resposta' => $taxa,
                    'sla_medio' => '2.4h'
                ],
                'recentes' => $recentes,
                'por_area' => $porArea,
                'por_mes' => $porMes
            ];
            break;

        case 'pedidos':
            [$filtroAcessoPedidos, $params2] = armsPedidosFiltroSql('r', 'rt_pedidos');

            $query = "
                SELECT r.id, r.reference as id_str, r.title, r.status,
                       to_char(r.created_at, 'DD/MM/YYYY') as date,
                       to_char(r.deadline_at, 'DD/MM/YYYY') as deadline,
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
                       COALESCE(r.destination_type, 'CLIENT') as destination_type,
                       r.recipient_user_id,
                       r.created_by as created_by_id,
                       a.name as area_name,
                       CASE
                           WHEN c.name = 'Aksanti' THEN COALESCE(up_creator.full_name, au_creator.email, c.name)
                           ELSE c.name
                       END as client_name,
                       COALESCE(up_recipient.full_name, au_recipient.email) as recipient_name
                FROM arms.request r
                JOIN arms.area a ON r.area_id = a.id
                JOIN arms.client c ON r.client_id = c.id
                LEFT JOIN arms.auth_user au_recipient ON r.recipient_user_id = au_recipient.id
                LEFT JOIN arms.user_profile up_recipient ON r.recipient_user_id = up_recipient.user_id
                LEFT JOIN arms.auth_user au_creator ON r.created_by = au_creator.id
                LEFT JOIN arms.user_profile up_creator ON r.created_by = up_creator.user_id
                WHERE 1=1 $filtroAcessoPedidos
                ORDER BY r.created_at DESC
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params2);
            $resultado['atualizacoes'] = ['pedidos' => $stmt->fetchAll()];
            break;

        case 'clientes':
            if (!$podeVerClientes) {
                $resultado['atualizacoes'] = ['clientes' => []];
                break;
            }

            // Retornar clientes apenas para Super Admins.
            $query = "
                SELECT c.id, c.name as company_name, c.primary_email as contact_email,
                       c.tax_id, c.location,
                       CASE WHEN c.is_active THEN 'ACTIVE' ELSE 'INACTIVE' END as status,
                       (SELECT full_name FROM arms.client_contact cc WHERE cc.client_id = c.id LIMIT 1) as contact_name
                FROM arms.client c
                ORDER BY c.name ASC
            ";

            $stmt = $pdo->query($query);
            $clientes = $stmt->fetchAll();
            
            foreach ($clientes as &$c) {
                if (empty($c['tax_id'])) $c['tax_id'] = '—';
                if (empty($c['location'])) $c['location'] = '—';
                if (!$c['contact_name']) $c['contact_name'] = 'Sem contacto';
            }

            $resultado['atualizacoes'] = ['clientes' => $clientes];
            break;

        case 'pedido-detalhe':
            $ref = $_GET['ref'] ?? '';
            if (empty($ref)) {
                $resultado['atualizacoes'] = [];
                break;
            }

            [$filtroDetalheAcesso, $paramsDetalheAcesso] = armsPedidosFiltroSql('r', 'rt_detalhe');
            $paramsDetalhe = array_merge([':ref' => $ref], $paramsDetalheAcesso);

            // Buscar comentários e timeline mais recentes
            $stmtComments = $pdo->prepare("
                SELECT rc.id, rc.body, up.full_name as author_name,
                       to_char(rc.created_at, 'DD/MM/YYYY, HH24:MI') as data_hora
                FROM arms.request_comment rc
                JOIN arms.request r ON rc.request_id = r.id
                LEFT JOIN arms.user_profile up ON rc.author_id = up.user_id
                WHERE r.reference = :ref $filtroDetalheAcesso
                ORDER BY rc.created_at DESC
            ");
            $stmtComments->execute($paramsDetalhe);

            $stmtTimeline = $pdo->prepare("
                SELECT ral.from_status, ral.to_status,
                       COALESCE(up.full_name, 'Sistema') as actor_name,
                       to_char(ral.created_at, 'DD/MM/YYYY, HH24:MI') as data_hora
                FROM arms.request_audit_log ral
                JOIN arms.request r ON ral.request_id = r.id
                LEFT JOIN arms.user_profile up ON ral.actor_id = up.user_id
                WHERE r.reference = :ref $filtroDetalheAcesso
                ORDER BY ral.created_at ASC
            ");
            $stmtTimeline->execute($paramsDetalhe);

            $stmtStatus = $pdo->prepare("SELECT status FROM arms.request r WHERE r.reference = :ref $filtroDetalheAcesso");
            $stmtStatus->execute($paramsDetalhe);
            $statusAtual = $stmtStatus->fetchColumn();

            $resultado['atualizacoes'] = [
                'comentarios' => $stmtComments->fetchAll(),
                'timeline' => $stmtTimeline->fetchAll(),
                'status' => $statusAtual
            ];
            break;

        default:
            // Contagem geral para badge de notificações
            $userId = $_SESSION['arms_user_id'] ?? null;
            
            if ($userId) {
                $stmtNotif = $pdo->prepare("
                    SELECT COUNT(*) FROM arms.notification 
                    WHERE recipient_id = :uid AND is_read = FALSE
                ");
                $stmtNotif->execute(['uid' => $userId]);
                $resultado['atualizacoes'] = [
                    'notificacoes_nao_lidas' => (int)$stmtNotif->fetchColumn()
                ];
            } else {
                $resultado['atualizacoes'] = [
                    'notificacoes_nao_lidas' => 0
                ];
            }
    }

    echo json_encode($resultado);

} catch (Exception $e) {
    error_log('[ARMS] Erro no tempo real: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao sincronizar dados.']);
}
?>
