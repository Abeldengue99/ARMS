<?php
$content = file_get_contents('pedidos.html');
$hex = bin2hex(substr($content, 0, 20));
echo "Hex: " . $hex . "\n";
echo "String: " . substr($content, 0, 20) . "\n";
?>
