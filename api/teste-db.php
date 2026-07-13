<?php

require_once 'auth.php';

if (!armsAuthIsAdmin()) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(404);
    echo "Not found\n";
    exit;
}

require_once 'db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== TESTE DE DRIVER POSTGRESQL ===\n\n";
echo "1. Drivers PDO disponíveis: " . implode(', ', PDO::getAvailableDrivers()) . "\n";
echo "2. Driver pgsql presente: " . (in_array('pgsql', PDO::getAvailableDrivers()) ? 'SIM' : 'NAO') . "\n\n";

try {
    echo "3. Conexão à BD: SUCESSO\n\n";

    $stmt = $pdo->query("SELECT code, name FROM arms.area ORDER BY name");
    $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "4. Áreas encontradas: " . count($areas) . "\n";
    foreach ($areas as $a) {
        echo "   - [{$a['code']}] {$a['name']}\n";
    }
} catch (Exception $e) {
    http_response_code(500);
    echo "3. ERRO: falha interna no diagnóstico.\n";
}
?>
