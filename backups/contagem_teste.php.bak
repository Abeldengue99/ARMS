<?php
require 'api/db.php';
$c = $pdo->query('SELECT COUNT(*) FROM arms.client')->fetchColumn();
$u = $pdo->query('SELECT COUNT(*) FROM arms.auth_user')->fetchColumn();
echo "Clientes: $c\nUtilizadores: $u\n";
