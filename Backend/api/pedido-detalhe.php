<?php
require_once 'db.php';
require_once 'auth.php';
require_once 'acesso-pedidos.php';
require_once 'configuracoes-servico.php';
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

if (!isset($_GET['ref'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Referência não fornecida']);
    exit;
}

armsAuthExigirLogin();

$ref = $_GET['ref'];
$sessionUserType = $_SESSION['arms_user_type'] ?? '';
$sessionUserId = (string)($_SESSION['arms_user_id'] ?? '');
$sessionIsAdmin = armsAuthBool($_SESSION['arms_is_admin'] ?? false);

try {
    $pdo->beginTransaction();
    [$filtroAcessoPedido, $paramsAcessoPedido] = armsPedidosFiltroSql('r', 'detalhe');

    // Pedido principal
    $sql = "SELECT r.id, r.reference, r.status, r.title, r.description, r.created_by as created_by_id,
            COALESCE(au_created.user_type, '') as created_by_type,
            COALESCE(au_created.is_admin, FALSE) as created_by_is_admin,
            COALESCE(r.destination_type, 'CLIENT') as destination_type,
            r.recipient_user_id,
            r.area_id,
            r.client_id,
            to_char(r.created_at, 'YYYY-MM-DD HH24:MI') as date,
            to_char(r.deadline_at, 'DD/MM/YYYY HH24:MI') as deadline,
            to_char(r.deadline_at, 'YYYY-MM-DD') as deadline_raw,
            (r.deadline_at < NOW() AND r.status IN ('SENT', 'RECEIVED')) as deadline_expirado,
            a.name as area_name,
            CASE
                WHEN COALESCE(r.destination_type, 'CLIENT') = 'AKSANTI' AND r.recipient_user_id IS NOT NULL
                    THEN COALESCE(up_recipient.full_name, au_recipient.email, c.name)
                ELSE c.name
            END as client_name,
            CASE
                WHEN COALESCE(r.destination_type, 'CLIENT') = 'AKSANTI' AND r.recipient_user_id IS NOT NULL
                    THEN COALESCE(au_recipient.email, r.client_email)
                ELSE c.primary_email
            END as client_email,
            r.client_email as raw_client_email,
            COALESCE(up_recipient.full_name, au_recipient.email) as recipient_name,
            au_recipient.email as recipient_email,
            u.full_name as created_by_name,
            au_created.email as created_by_email,
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
            (
                SELECT up_latest.full_name
                FROM arms.request_response rr_latest
                LEFT JOIN arms.user_profile up_latest ON rr_latest.responded_by = up_latest.user_id
                WHERE rr_latest.request_id = r.id
                ORDER BY rr_latest.created_at DESC
                LIMIT 1
            ) as latest_response_by_name,
            COALESCE((
                SELECT up_sent.full_name
                FROM arms.request_audit_log sent_log
                LEFT JOIN arms.user_profile up_sent ON sent_log.actor_id = up_sent.user_id
                WHERE sent_log.request_id = r.id
                  AND sent_log.to_status = 'SENT'
                ORDER BY sent_log.created_at DESC
                LIMIT 1
            ), u.full_name) as sent_by_name
            FROM arms.request r
            LEFT JOIN arms.area a ON r.area_id = a.id
            LEFT JOIN arms.client c ON r.client_id = c.id
            LEFT JOIN arms.user_profile u ON r.created_by = u.user_id
            LEFT JOIN arms.auth_user au_created ON r.created_by = au_created.id
            LEFT JOIN arms.auth_user au_recipient ON r.recipient_user_id = au_recipient.id
            LEFT JOIN arms.user_profile up_recipient ON r.recipient_user_id = up_recipient.user_id
            WHERE r.reference = :ref $filtroAcessoPedido";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([':ref' => $ref], $paramsAcessoPedido));
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        echo json_encode(['sucesso' => false, 'erro' => 'Pedido não encontrado']);
        exit;
    }

    $req_id = $pedido['id'];

    // Timeline
    $sql = "SELECT ral.to_status, to_char(ral.created_at, 'YYYY-MM-DD HH24:MI') as data_hora,
            COALESCE(u.full_name, 'Sistema') as actor_name,
            COALESCE(au.user_type, '') as actor_type,
            CASE
                WHEN ral.to_status = 'CLIENT_RESPONDED' THEN (
                    SELECT rr_event.decision
                    FROM arms.request_response rr_event
                    WHERE rr_event.request_id = ral.request_id
                    ORDER BY ABS(EXTRACT(EPOCH FROM (rr_event.created_at - ral.created_at))) ASC
                    LIMIT 1
                )
                ELSE NULL
            END as response_decision
            FROM arms.request_audit_log ral
            LEFT JOIN arms.user_profile u ON ral.actor_id = u.user_id
            LEFT JOIN arms.auth_user au ON ral.actor_id = au.id
            WHERE ral.request_id = ?";

    $timelineParams = [$req_id];

    // Quem ENVIOU o pedido (created_by) vê todos os status.
    // Quem RECEBEU o pedido (não é o criador e não é admin) só vê:
    //   - a data que recebeu (RECEIVED)
    //   - a data que respondeu (CLIENT_RESPONDED, ACCEPTED, REJECTED)
    $pedidoCriadoPeloUtilizador = (string)($pedido['created_by_id'] ?? '') === $sessionUserId;

    if (!$pedidoCriadoPeloUtilizador) {
        // Quem recebeu o pedido não vê DRAFT. Vê SENT, RECEIVED, e as respostas.
        $sql .= " AND ral.to_status IN ('SENT', 'RECEIVED', 'CLIENT_RESPONDED', 'ACCEPTED', 'REJECTED')";
    }

    $sql .= " ORDER BY ral.created_at ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($timelineParams);
    $timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $isReceiver = !$pedidoCriadoPeloUtilizador;

    if ($isReceiver) {
        $temReceived = false;
        foreach ($timeline as $evento) {
            if ($evento['to_status'] === 'RECEIVED') {
                $temReceived = true;
                break;
            }
        }

        // Se houver SENT e RECEIVED, para quem recebe, mantemos apenas o RECEIVED ou o SENT convertido.
        $timelineLimpa = [];
        foreach ($timeline as $evento) {
            if ($evento['to_status'] === 'SENT') {
                if ($temReceived) continue; // Ignora SENT se já tiver RECEIVED
                $evento['to_status'] = 'RECEIVED'; // Converte visualmente o SENT para RECEIVED
                $evento['actor_name'] = $pedido['sent_by_name'] ?: $pedido['created_by_name'];
            }
            if ($evento['to_status'] === 'RECEIVED') {
                $evento['actor_name'] = $pedido['sent_by_name'] ?: $pedido['created_by_name'];
            }
            $timelineLimpa[] = $evento;
        }
        $timeline = $timelineLimpa;

        $temRespostaFinal = false;
        foreach ($timeline as $evento) {
            if (in_array($evento['to_status'], ['ACCEPTED', 'REJECTED'], true)) {
                $temRespostaFinal = true;
                break;
            }
        }

        if ($temRespostaFinal) {
            $timeline = array_values(array_filter($timeline, function ($evento) {
                return $evento['to_status'] !== 'CLIENT_RESPONDED';
            }));
        }
    }

    // Comentários
    $sql = "SELECT
            rc.id,
            rc.author_id,
            rc.body,
            rc.edit_count,
            to_char(rc.created_at, 'YYYY-MM-DD HH24:MI') as data_hora,
            to_char(rc.edited_at, 'YYYY-MM-DD HH24:MI') as edited_at,
            u.full_name as author_name,
            editor.full_name as edited_by_name
            FROM arms.request_comment rc
            LEFT JOIN arms.user_profile u ON rc.author_id = u.user_id
            LEFT JOIN arms.user_profile editor ON rc.edited_by = editor.user_id
            WHERE rc.request_id = ? ORDER BY rc.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$req_id]);
    $comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($comentarios as &$comentario) {
        $comentario['can_edit'] = $sessionIsAdmin || strcasecmp((string)$comentario['author_id'], $sessionUserId) === 0;
    }
    unset($comentario);

    // Anexos
    $sql = "SELECT
            a.id,
            a.file_name,
            a.size_bytes,
            a.uploaded_by,
            a.update_count,
            to_char(a.created_at, 'YYYY-MM-DD HH24:MI') as data_hora,
            to_char(a.updated_at, 'YYYY-MM-DD HH24:MI') as updated_at,
            u.full_name as uploaded_by_name,
            updater.full_name as updated_by_name
            FROM arms.attachment a
            LEFT JOIN arms.user_profile u ON a.uploaded_by = u.user_id
            LEFT JOIN arms.user_profile updater ON a.updated_by = updater.user_id
            WHERE a.request_id = ? AND a.response_id IS NULL ORDER BY a.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$req_id]);
    $anexos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($anexos as &$anexo) {
        $anexo['can_update'] = $sessionIsAdmin || strcasecmp((string)$anexo['uploaded_by'], $sessionUserId) === 0;
    }
    unset($anexo);

    // Respostas Formais do Cliente
    $sql = "SELECT rr.decision as status_decision, rr.body as message, to_char(rr.created_at, 'YYYY-MM-DD HH24:MI') as data_hora,
            u.full_name as responded_by_name FROM arms.request_response rr
            LEFT JOIN arms.user_profile u ON rr.responded_by = u.user_id
            WHERE rr.request_id = ? ORDER BY rr.created_at ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$req_id]);
    $respostas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $comentarioCacheDias = 2;
    try {
        $stmtCache = $pdo->prepare("
            SELECT retention_days
            FROM arms.data_retention_policy
            WHERE policy_key = 'comment_draft_cache'
        ");
        $stmtCache->execute();
        $diasConfigurados = $stmtCache->fetchColumn();
        if ($diasConfigurados !== false) {
            $comentarioCacheDias = max(1, (int)$diasConfigurados);
        }
    } catch (Throwable $e) {
        $comentarioCacheDias = 2;
    }

    $configuracoes = [
        'attachment_max_size_mb' => armsConfiguracaoInteiro($pdo, 'attachment_max_size_mb', 50, 1, 10240),
        'comment_draft_cache_days' => $comentarioCacheDias,
    ];

    $pdo->commit();

    echo json_encode([
        'sucesso' => true,
        'dados' => $pedido,
        'timeline' => $timeline,
        'comentarios' => $comentarios,
        'anexos' => $anexos,
        'respostas' => $respostas,
        'configuracoes' => $configuracoes
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('[ARMS] Erro ao carregar detalhe do pedido: ' . $e->getMessage());
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao carregar detalhes do pedido.']);
}
?>
