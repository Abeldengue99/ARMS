<?php

$files = glob('*.html');
$version = '?v=' . time();

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Replace .css" with .css?v=..."
    // Replace .css?v=X" with .css?v=..."
    $content = preg_replace('/\.css(\?v=\d+)?\"/', '.css' . $version . '"', $content);
    
    // Replace .js" with .js?v=..."
    // Replace .js?v=X" with .js?v=..."
    $content = preg_replace('/\.js(\?v=\d+)?\"/', '.js' . $version . '"', $content);
    
    file_put_contents($file, $content);
}

echo "Cache busting applied with " . $version . "\n";
?>
