<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo json_encode([
    'sucesso' => true,
    'app' => 'ARMS',
    'deploy_marker' => '2026-08-23T20:05:00+01:00',
    'frontend' => [
        'layout_css' => 'v=16',
        'responsivo_css' => 'v=11',
        'animacoes_css' => 'v=11',
        'sidebar_js' => 'v=17',
        'pedido_detalhe_js' => 'v=16',
        'pwa_js' => 'v=3',
        'service_worker' => '/sw.js',
        'anti_flicker_js' => 'v=16',
    ],
    'backend' => [
        'api' => true,
        'session_driver_env' => getenv('ARMS_SESSION_DRIVER') ?: 'files',
        'session_name_env' => getenv('ARMS_SESSION_NAME') ?: 'PHPSESSID'
    ]
]);
?>
