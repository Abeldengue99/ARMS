<?php
header('Content-Type: application/json; charset=utf-8');

// Em producao, definir estas variaveis no ambiente do servidor.
$host = getenv('ARMS_DB_HOST') ?: 'localhost';
$db = getenv('ARMS_DB_NAME') ?: 'ARMS — Aksanti Request Management System';
$user = getenv('ARMS_DB_USER') ?: 'postgres';
$pass = getenv('ARMS_DB_PASS') ?: '5850';
$port = getenv('ARMS_DB_PORT') ?: '5432';

try {
    $dsn = "pgsql:host=$host;port=$port;dbname='$db'";
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
        'erro' => 'Erro interno ao ligar à base de dados.'
    ]);
    exit;
}
?>
