<?php
$files = glob('*.html');

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Remove the button block
    // The button looks like:
    // <button id="btn-menu-mobile" class="btn-menu-mobile">
    //     <svg ...></svg>
    // </button>
    
    $pattern = '/<button id="btn-menu-mobile"[^>]*>.*?<\/button>\s*/is';
    
    if (preg_match($pattern, $content)) {
        $content = preg_replace($pattern, '', $content);
        file_put_contents($file, $content);
        echo "Removed from $file\n";
    }
}
echo "Done!\n";
?>
