<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo json_encode([
    'sucesso' => true,
    'app' => 'ARMS',
    'deploy_marker' => '2026-08-22T13:58:49+01:00',
    'backend' => [
        'api' => true,
        'session_driver_env' => getenv('ARMS_SESSION_DRIVER') ?: 'files',
        'session_name_env' => getenv('ARMS_SESSION_NAME') ?: 'PHPSESSID'
    ]
]);
?>
