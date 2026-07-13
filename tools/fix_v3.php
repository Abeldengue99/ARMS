<?php
// Force a different version number on pedidos.html and areas.html
$files = ['pedidos.html', 'areas.html'];

foreach ($files as $file) {
    $content = file_get_contents($file);
    // Replace ?v=2 with ?v=3
    $content = str_replace('?v=2', '?v=3', $content);
    file_put_contents($file, $content);
    echo "Updated $file to ?v=3\n";
}

echo "Done!\n";
?>
