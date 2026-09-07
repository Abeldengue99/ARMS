<?php ini_set('display_errors', 1); error_reporting(E_ALL); require '../Backend/api/db.php'; echo '<pre>'; print_r($_ENV); print_r($_SERVER['ARMS_DB_NAME'] ?? 'NOT SET'); echo '</pre>'; ?>
