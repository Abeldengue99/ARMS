<?php
require 'Backend/api/db.php';
$stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_schema = 'arms' AND table_name = 'request' AND column_name IN ('closed_at', 'created_at', 'deadline_at') ORDER BY column_name");
while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $r['column_name'] . ' => ' . $r['data_type'] . PHP_EOL;
}
$stmtTz = $pdo->query("SHOW timezone");
echo 'PostgreSQL timezone: ' . $stmtTz->fetchColumn() . PHP_EOL;
?> 
