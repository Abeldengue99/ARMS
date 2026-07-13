<?php
require_once 'db.php';
require_once 'auth.php';
require_once 'permissoes.php';
require_once 'seguranca-servico.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    armsExigirPermissao($pdo, 'seguranca.gerir', 'Não tem permissão para gerir segurança.');
    armsSegurancaGarantirTabelas($pdo);

    $acao = $_GET['acao'] ?? 'resumo';

    if ($acao === 'desbloquear') {
        $entrada = json_decode(file_get_contents('php://input'), true) ?: [];
        $email = strtolower(trim($entrada['email'] ?? ''));
        $ip = trim($entrada['ip_address'] ?? $entrada['ip'] ?? '');

        if (!$email || !$ip) {
            echo json_encode(['sucesso' => false, 'erro' => 'Dados de desbloqueio inválidos.']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE arms.security_login_lock
            SET unlocked_at = NOW(),
                unlocked_by = :user_id,
                blocked_until = NOW()
            WHERE email = :email
              AND ip_address = :ip
              AND unlocked_at IS NULL
        ");
        $stmt->execute([
            ':user_id' => $_SESSION['arms_user_id'] ?? null,
            ':email' => $email,
            ':ip' => $ip,
        ]);

        echo json_encode(['sucesso' => true, 'mensagem' => 'Bloqueio removido com sucesso.']);
        exit;
    }

    $bloqueios = $pdo->query("
        SELECT email, ip_address, attempts,
               to_char(blocked_until, 'DD/MM/YYYY HH24:MI') AS blocked_until,
               GREATEST(0, CEIL(EXTRACT(EPOCH FROM (blocked_until - NOW())) / 60))::INT AS minutos_restantes
        FROM arms.security_login_lock
        WHERE unlocked_at IS NULL
          AND blocked_until > NOW()
        ORDER BY blocked_until DESC
        LIMIT 20
    ")->fetchAll() ?: [];

    $alertas = $pdo->query("
        SELECT id, email, ip_address, severity, message, status,
               to_char(created_at, 'DD/MM/YYYY HH24:MI') AS created_at
        FROM arms.security_alert
        WHERE status = 'OPEN'
        ORDER BY created_at DESC
        LIMIT 20
    ")->fetchAll() ?: [];

    $sessoes = $pdo->query("
        SELECT s.session_hash,
               COALESCE(up.full_name, au.email) AS nome,
               au.email,
               au.user_type,
               au.is_admin,
               s.ip_address,
               s.user_agent,
               to_char(s.started_at, 'DD/MM/YYYY HH24:MI') AS started_at,
               to_char(s.last_seen_at, 'DD/MM/YYYY HH24:MI') AS last_seen_at
        FROM arms.security_active_session s
        JOIN arms.auth_user au ON au.id = s.user_id
        LEFT JOIN arms.user_profile up ON up.user_id = au.id
        WHERE s.ended_at IS NULL
          AND s.last_seen_at >= NOW() - INTERVAL '60 minutes'
        ORDER BY s.last_seen_at DESC
        LIMIT 30
    ")->fetchAll() ?: [];

    $historico = $pdo->query("
        SELECT e.email,
               COALESCE(up.full_name, e.email) AS nome,
               e.ip_address,
               e.event_type,
               e.success,
               COALESCE(e.reason, '') AS reason,
               to_char(e.created_at, 'DD/MM/YYYY HH24:MI') AS created_at
        FROM arms.security_login_event e
        LEFT JOIN arms.user_profile up ON up.user_id = e.user_id
        ORDER BY e.created_at DESC
        LIMIT 40
    ")->fetchAll() ?: [];

    $falhas24h = (int)$pdo->query("
        SELECT COUNT(*)
        FROM arms.security_login_event
        WHERE success = FALSE
          AND created_at >= NOW() - INTERVAL '24 hours'
    ")->fetchColumn();

    echo json_encode([
        'sucesso' => true,
        'kpis' => [
            'bloqueios' => count($bloqueios),
            'alertas' => count($alertas),
            'sessoes' => count($sessoes),
            'falhas_24h' => $falhas24h,
        ],
        'bloqueios' => $bloqueios,
        'alertas' => $alertas,
        'sessoes' => $sessoes,
        'historico' => $historico,
    ]);
} catch (Exception $e) {
    error_log('[ARMS] Erro na segurança automatizada: ' . $e->getMessage());
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao carregar segurança.']);
}
?>
