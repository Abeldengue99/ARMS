<?php

$files = glob('*.html');

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Remove the ?v=1783685070
    $content = str_replace('?v=1783685070', '', $content);
    
    file_put_contents($file, $content);
}

// Restore dashboard.html to its original ?v=2 state
$dashboard = file_get_contents('dashboard.html');
$dashboard = preg_replace('/\.css\"/', '.css?v=2"', $dashboard);
$dashboard = preg_replace('/\.js\"/', '.js"', $dashboard); // Except dashboard didn't have ?v=2 on JS previously, only CSS. But wait! Let me check the backup.
file_put_contents('dashboard.html', $dashboard);

echo "Reverted cache busting.\n";
?>
