<?php

function armsAuthIniciarSessao() {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $sessionDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'arms-sessions';

    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0700, true);
    }

    if (is_dir($sessionDir) && is_writable($sessionDir)) {
        session_save_path($sessionDir);
    }

    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_only_cookies', '1');

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

function armsAuthIsAdmin() {
    armsAuthIniciarSessao();
    return armsAuthBool($_SESSION['arms_is_admin'] ?? false);
}

function armsAuthExigirLogin() {
    if (!armsAuthLogado()) {
        echo json_encode(['sucesso' => false, 'erro' => 'Não autenticado.']);
        exit;
    }
}

function armsAuthExigirAdmin() {
    armsAuthExigirLogin();

    if (!armsAuthIsAdmin()) {
        echo json_encode(['sucesso' => false, 'erro' => 'Apenas Super Admins podem aceder a esta área.']);
        exit;
    }
}
?>
