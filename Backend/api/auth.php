<?php

class ArmsPostgresSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function open(string $path, string $name): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read(string $id): string|false {
        try {
            $stmt = $this->pdo->prepare("SELECT data FROM arms.php_session WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $data = $stmt->fetchColumn();

            return $data === false ? '' : (string) $data;
        } catch (Throwable $e) {
            error_log('[ARMS] Erro ao ler sessao PostgreSQL: ' . $e->getMessage());
            return '';
        }
    }

    public function write(string $id, string $data): bool {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO arms.php_session (id, data, last_activity)
                VALUES (:id, :data, NOW())
                ON CONFLICT (id) DO UPDATE
                SET data = EXCLUDED.data,
                    last_activity = NOW()
            ");

            return $stmt->execute([':id' => $id, ':data' => $data]);
        } catch (Throwable $e) {
            error_log('[ARMS] Erro ao gravar sessao PostgreSQL: ' . $e->getMessage());
            return false;
        }
    }

    public function destroy(string $id): bool {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM arms.php_session WHERE id = :id");
            return $stmt->execute([':id' => $id]);
        } catch (Throwable $e) {
            error_log('[ARMS] Erro ao destruir sessao PostgreSQL: ' . $e->getMessage());
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM arms.php_session
                WHERE last_activity < NOW() - (:ttl * INTERVAL '1 second')
            ");
            $stmt->execute([':ttl' => max(60, $max_lifetime)]);

            return $stmt->rowCount();
        } catch (Throwable $e) {
            error_log('[ARMS] Erro ao limpar sessoes PostgreSQL: ' . $e->getMessage());
            return false;
        }
    }

    public function validateId(string $id): bool {
        try {
            $stmt = $this->pdo->prepare("SELECT 1 FROM arms.php_session WHERE id = :id");
            $stmt->execute([':id' => $id]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('[ARMS] Erro ao validar sessao PostgreSQL: ' . $e->getMessage());
            return false;
        }
    }

    public function updateTimestamp(string $id, string $data): bool {
        return $this->write($id, $data);
    }
}

function armsAuthHttps(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($forwardedProto && str_contains($forwardedProto, 'https')) {
        return true;
    }

    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on'
        || strtolower((string) ($_SERVER['HTTP_FRONT_END_HTTPS'] ?? '')) === 'on';
}

function armsAuthGarantirTabelaSessao(PDO $pdo): void {
    static $garantida = false;

    if ($garantida) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS arms.php_session (
            id VARCHAR(128) PRIMARY KEY,
            data TEXT NOT NULL DEFAULT '',
            last_activity TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )
    ");

    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_php_session_last_activity
        ON arms.php_session (last_activity)
    ");

    $garantida = true;
}

function armsAuthConfigurarSessaoBanco(PDO $pdo): bool {
    static $configurada = false;

    if ($configurada) {
        return true;
    }

    try {
        armsAuthGarantirTabelaSessao($pdo);
        session_set_save_handler(new ArmsPostgresSessionHandler($pdo), true);
        $configurada = true;
        return true;
    } catch (Throwable $e) {
        error_log('[ARMS] Nao foi possivel configurar sessao PostgreSQL: ' . $e->getMessage());
        return false;
    }
}

function armsAuthConfigurarSessaoFicheiro(): void {
    $sessionDir = getenv('ARMS_SESSION_PATH') ?: (__DIR__ . '/../tmp/sessions');

    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0700, true);
    }

    if (is_dir($sessionDir) && is_writable($sessionDir)) {
        session_save_path($sessionDir);
    }
}

function armsAuthIniciarSessao() {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $driver = strtolower((string) (getenv('ARMS_SESSION_DRIVER') ?: 'files'));
    $pdo = $GLOBALS['pdo'] ?? null;

    if ($driver !== 'files' && $pdo instanceof PDO) {
        if (!armsAuthConfigurarSessaoBanco($pdo)) {
            armsAuthConfigurarSessaoFicheiro();
        }
    } else {
        armsAuthConfigurarSessaoFicheiro();
    }

    $https = armsAuthHttps();

    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_only_cookies', '1');

    session_name(getenv('ARMS_SESSION_NAME') ?: 'PHPSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    @session_start();
}

function armsAuthBool($valor) {
    return $valor === true || $valor === 1 || $valor === '1' || $valor === 't' || $valor === 'true' || $valor === 'TRUE';
}

function armsAuthLogado() {
    armsAuthIniciarSessao();
    return !empty($_SESSION['arms_logado']) && !empty($_SESSION['arms_user_id']);
}

function armsAuthLogFalhaAutenticacao(string $origem = ''): void {
    $sessionName = session_name() ?: (getenv('ARMS_SESSION_NAME') ?: 'PHPSESSID');
    $sessionId = session_id();
    $sessionHash = $sessionId ? substr(hash('sha256', $sessionId), 0, 12) : '';

    error_log('[ARMS] Falha de autenticacao' . ($origem ? " em {$origem}" : '') . ': ' . json_encode([
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'session_driver' => getenv('ARMS_SESSION_DRIVER') ?: 'files',
        'session_name' => $sessionName,
        'cookie_present' => array_key_exists($sessionName, $_COOKIE),
        'session_status' => session_status(),
        'session_hash' => $sessionHash,
        'has_arms_logado' => array_key_exists('arms_logado', $_SESSION),
        'has_arms_user_id' => array_key_exists('arms_user_id', $_SESSION),
    ], JSON_UNESCAPED_SLASHES));
}

function armsAuthIsAdmin() {
    armsAuthIniciarSessao();
    return armsAuthBool($_SESSION['arms_is_admin'] ?? false);
}

function armsAuthExigirLogin() {
    if (!armsAuthLogado()) {
        armsAuthLogFalhaAutenticacao('armsAuthExigirLogin');
        http_response_code(401);
        echo json_encode(['sucesso' => false, 'erro' => 'Nao autenticado.', 'codigo' => 'AUTH_REQUIRED']);
        exit;
    }
}

function armsAuthExigirAdmin() {
    armsAuthExigirLogin();

    if (!armsAuthIsAdmin()) {
        http_response_code(403);
        echo json_encode(['sucesso' => false, 'erro' => 'Apenas Super Admins podem aceder a esta area.']);
        exit;
    }
}
?>
