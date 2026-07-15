<?php
require_once __DIR__ . '/../api/db.php';

try {
    echo "--- request_recipient ---\n";
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'request_recipient' AND table_schema = 'arms'");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['column_name'] . " - " . $row['data_type'] . "\n";
    }

    echo "\n--- area_membership ---\n";
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'area_membership' AND table_schema = 'arms'");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['column_name'] . " - " . $row['data_type'] . "\n";
    }

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
