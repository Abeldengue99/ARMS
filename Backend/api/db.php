<?php
header('Content-Type: application/json; charset=utf-8');

function armsDbCarregarEnv(string $path): void {
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $linhas = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($linhas === false) {
        return;
    }

    foreach ($linhas as $linha) {
        $linha = trim($linha);
        if ($linha === '' || str_starts_with($linha, '#') || !str_contains($linha, '=')) {
            continue;
        }

        [$chave, $valor] = array_map('trim', explode('=', $linha, 2));
        if ($chave === '' || getenv($chave) !== false) {
            continue;
        }

        $valor = trim($valor, "\"'");
        putenv($chave . '=' . $valor);
        $_ENV[$chave] = $valor;
        $_SERVER[$chave] = $valor;
    }
}

function armsDbEnv(string $chave, ?string $padrao = null): ?string {
    $valor = getenv($chave);
    return ($valor === false || $valor === '') ? $padrao : $valor;
}

armsDbCarregarEnv(__DIR__ . '/../../.env');
armsDbCarregarEnv(__DIR__ . '/../.env');

$databaseUrl = armsDbEnv('DATABASE_URL') ?: armsDbEnv('POSTGRES_URL') ?: '';

$host = armsDbEnv('ARMS_DB_HOST', 'localhost');
$db = armsDbEnv('ARMS_DB_NAME', 'arms_db');
$user = armsDbEnv('ARMS_DB_USER', 'postgres');
$pass = armsDbEnv('ARMS_DB_PASS', '');
$port = armsDbEnv('ARMS_DB_PORT', '5432');

if ($databaseUrl) {
    $parts = parse_url($databaseUrl);

    if (is_array($parts)) {
        $host = $parts['host'] ?? $host;
        $port = isset($parts['port']) ? (string) $parts['port'] : $port;
        $user = isset($parts['user']) ? rawurldecode($parts['user']) : $user;
        $pass = isset($parts['pass']) ? rawurldecode($parts['pass']) : $pass;
        $path = isset($parts['path']) ? ltrim($parts['path'], '/') : '';
        $db = $path !== '' ? rawurldecode($path) : $db;
    }
}

try {
    $dsn = "pgsql:host={$host};port={$port};dbname={$db}";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    $pdo->exec("SET search_path TO arms, public");

    try {
        $pdo->exec("SET client_encoding TO 'UTF8'");
    } catch (PDOException $e) {
        error_log('[ARMS] Could not set client_encoding to UTF8: ' . $e->getMessage());
    }
} catch (PDOException $e) {
    error_log('[ARMS] Falha na conexao a base de dados: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro interno ao ligar a base de dados.'
    ]);
    exit;
}
?>
