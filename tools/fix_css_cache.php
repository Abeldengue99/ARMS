<?php
// Add ?v=2 to all CSS and JS references in all HTML files (except dashboard which already has it)
$files = glob('*.html');

foreach ($files as $file) {
    if ($file === 'dashboard.html') continue; // Already has ?v=2
    
    $content = file_get_contents($file);
    
    // Add ?v=2 to .css" references that don't already have a version
    $content = preg_replace('/\.css"/', '.css?v=2"', $content);
    
    file_put_contents($file, $content);
    echo "Fixed: $file\n";
}

// Also add missing CSS files to dashboard.html
$dashboard = file_get_contents('dashboard.html');

// Check if responsivo.css is missing from dashboard
if (strpos($dashboard, 'responsivo.css') === false) {
    $dashboard = str_replace(
        '<link rel="stylesheet" href="css/animacoes.css?v=2">',
        '<link rel="stylesheet" href="css/animacoes.css?v=2">' . "\r\n" . '    <link rel="stylesheet" href="css/responsivo.css?v=2">',
        $dashboard
    );
    file_put_contents('dashboard.html', $dashboard);
    echo "Added responsivo.css to dashboard.html\n";
}

echo "Done!\n";
?>
