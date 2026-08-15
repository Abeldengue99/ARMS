<?php
function armsSegurancaIp(): string {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ip = explode(',', (string)$ip)[0];
    return substr(trim($ip) ?: 'desconhecido', 0, 64);
}

function armsSegurancaUserAgent(): string {
    return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'desconhecido'), 0, 500);
}

function armsSegurancaSessionHash(): string {
    return hash('sha256', session_id());
}

function armsSegurancaGarantirTabelas(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS arms.security_login_event (
            id BIGSERIAL PRIMARY KEY,
            user_id UUID NULL REFERENCES arms.auth_user(id) ON DELETE SET NULL,
            email VARCHAR(190) NOT NULL,
            ip_address VARCHAR(64) NOT NULL,
            user_agent TEXT,
            event_type VARCHAR(32) NOT NULL,
            success BOOLEAN NOT NULL DEFAULT FALSE,
            reason TEXT,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS arms.security_login_lock (
            id BIGSERIAL PRIMARY KEY,
            email VARCHAR(190) NOT NULL,
            ip_address VARCHAR(64) NOT NULL,
            attempts INTEGER NOT NULL DEFAULT 0,
            blocked_until TIMESTAMPTZ NOT NULL,
            last_attempt_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            unlocked_by UUID NULL REFERENCES arms.auth_user(id) ON DELETE SET NULL,
            unlocked_at TIMESTAMPTZ NULL,
            UNIQUE (email, ip_address)
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS arms.security_alert (
            id BIGSERIAL PRIMARY KEY,
            user_id UUID NULL REFERENCES arms.auth_user(id) ON DELETE SET NULL,
            email VARCHAR(190) NOT NULL,
            ip_address VARCHAR(64) NOT NULL,
            severity VARCHAR(16) NOT NULL DEFAULT 'MEDIUM',
            message TEXT NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'OPEN',
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            resolved_at TIMESTAMPTZ NULL,
            resolved_by UUID NULL REFERENCES arms.auth_user(id) ON DELETE SET NULL
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS arms.security_active_session (
            session_hash VARCHAR(64) PRIMARY KEY,
            user_id UUID NOT NULL REFERENCES arms.auth_user(id) ON DELETE CASCADE,
            ip_address VARCHAR(64) NOT NULL,
            user_agent TEXT,
            started_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            last_seen_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            ended_at TIMESTAMPTZ NULL
        )
    ");
}

function armsSegurancaBloqueioAtual(PDO $pdo, string $email): ?array {
    armsSegurancaGarantirTabelas($pdo);
    $stmt = $pdo->prepare("
        SELECT email, ip_address, attempts, blocked_until,
               GREATEST(0, CEIL(EXTRACT(EPOCH FROM (blocked_until - NOW())) / 60))::INT AS minutos_restantes
        FROM arms.security_login_lock
        WHERE email = :email
          AND ip_address = :ip
          AND unlocked_at IS NULL
          AND blocked_until > NOW()
        LIMIT 1
    ");
    $stmt->execute([':email' => strtolower(trim($email)), ':ip' => armsSegurancaIp()]);
    $bloqueio = $stmt->fetch();
    return $bloqueio ?: null;
}

function armsSegurancaRegistarEvento(PDO $pdo, string $email, ?string $userId, string $tipo, bool $sucesso, string $motivo = ''): void {
    armsSegurancaGarantirTabelas($pdo);
    $stmt = $pdo->prepare("
        INSERT INTO arms.security_login_event (user_id, email, ip_address, user_agent, event_type, success, reason)
        VALUES (:user_id, :email, :ip, :agent, :event_type, :success, :reason)
    ");
    $stmt->execute([
        ':user_id' => $userId,
        ':email' => strtolower(trim($email)),
        ':ip' => armsSegurancaIp(),
        ':agent' => armsSegurancaUserAgent(),
        ':event_type' => $tipo,
        ':success' => $sucesso ? 'true' : 'false',
        ':reason' => $motivo ?: null,
    ]);
}

function armsSegurancaRegistarFalha(PDO $pdo, string $email, ?string $userId, string $motivo): void {
    $email = strtolower(trim($email));
    $ip = armsSegurancaIp();
    armsSegurancaRegistarEvento($pdo, $email, $userId, 'LOGIN_FAILED', false, $motivo);

    $stmtTentativas = $pdo->prepare("
        SELECT COUNT(*)
        FROM arms.security_login_event
        WHERE email = :email
          AND ip_address = :ip
          AND success = FALSE
          AND event_type IN ('LOGIN_FAILED', 'LOGIN_INACTIVE')
          AND created_at >= NOW() - INTERVAL '15 minutes'
    ");
    $stmtTentativas->execute([':email' => $email, ':ip' => $ip]);
    $tentativas = (int)$stmtTentativas->fetchColumn();

    if ($tentativas >= 5) {
        $stmtLock = $pdo->prepare("
            INSERT INTO arms.security_login_lock (email, ip_address, attempts, blocked_until, last_attempt_at)
            VALUES (:email, :ip, :attempts, NOW() + INTERVAL '30 minutes', NOW())
            ON CONFLICT (email, ip_address) DO UPDATE
            SET attempts = EXCLUDED.attempts,
                blocked_until = EXCLUDED.blocked_until,
                last_attempt_at = NOW(),
                unlocked_at = NULL,
                unlocked_by = NULL
        ");
        $stmtLock->execute([':email' => $email, ':ip' => $ip, ':attempts' => $tentativas]);

        $stmtAlerta = $pdo->prepare("
            INSERT INTO arms.security_alert (user_id, email, ip_address, severity, message)
            VALUES (:user_id, :email, :ip, 'HIGH', :message)
        ");
        $stmtAlerta->execute([
            ':user_id' => $userId,
            ':email' => $email,
            ':ip' => $ip,
            ':message' => "Conta bloqueada temporariamente após {$tentativas} tentativas falhadas em 15 minutos.",
        ]);
    }
}

function armsSegurancaRegistarLoginSucesso(PDO $pdo, string $userId, string $email): void {
    armsSegurancaGarantirTabelas($pdo);
    armsSegurancaRegistarEvento($pdo, $email, $userId, 'LOGIN_SUCCESS', true);

    $stmt = $pdo->prepare("DELETE FROM arms.security_login_lock WHERE email = :email AND ip_address = :ip");
    $stmt->execute([':email' => strtolower(trim($email)), ':ip' => armsSegurancaIp()]);

    $sessao = $pdo->prepare("
        INSERT INTO arms.security_active_session (session_hash, user_id, ip_address, user_agent)
        VALUES (:session_hash, :user_id, :ip, :agent)
        ON CONFLICT (session_hash) DO UPDATE
        SET user_id = EXCLUDED.user_id,
            ip_address = EXCLUDED.ip_address,
            user_agent = EXCLUDED.user_agent,
            last_seen_at = NOW(),
            ended_at = NULL
    ");
    $sessao->execute([
        ':session_hash' => armsSegurancaSessionHash(),
        ':user_id' => $userId,
        ':ip' => armsSegurancaIp(),
        ':agent' => armsSegurancaUserAgent(),
    ]);
}

function armsSegurancaAtualizarSessao(PDO $pdo, ?string $userId = null): void {
    if (empty($userId) || session_status() !== PHP_SESSION_ACTIVE || !session_id()) return;
    armsSegurancaGarantirTabelas($pdo);
    $stmt = $pdo->prepare("
        UPDATE arms.security_active_session
        SET last_seen_at = NOW()
        WHERE session_hash = :session_hash
          AND user_id = :user_id
          AND ended_at IS NULL
    ");
    $stmt->execute([':session_hash' => armsSegurancaSessionHash(), ':user_id' => $userId]);
}

function armsSegurancaTerminarSessaoAtual(PDO $pdo): void {
    if (session_status() !== PHP_SESSION_ACTIVE || !session_id()) return;
    armsSegurancaGarantirTabelas($pdo);
    $email = $_SESSION['arms_user_email'] ?? '';
    $userId = $_SESSION['arms_user_id'] ?? null;
    if ($email) {
        armsSegurancaRegistarEvento($pdo, $email, $userId, 'LOGOUT', true);
    }
    $stmt = $pdo->prepare("UPDATE arms.security_active_session SET ended_at = NOW() WHERE session_hash = :session_hash");
    $stmt->execute([':session_hash' => armsSegurancaSessionHash()]);
}
?>
