<?php
require_once 'auth.php';

header('X-Robots-Tag: noindex, nofollow', true);

if (!armsAuthIsAdmin()) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

phpinfo();
?>
