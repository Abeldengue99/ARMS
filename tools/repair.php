<?php
$files = glob("*.html");

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Fix css and js versioning
    $content = str_replace("cssóv=", "css?v=", $content);
    $content = str_replace("jsóv=", "js?v=", $content);
    
    // Fix known JS ternaries that were corrupted
    $content = str_replace("naoLidas > 0 é ", "naoLidas > 0 ? ", $content);
    $content = str_replace("naoLidas > 1 é ", "naoLidas > 1 ? ", $content);
    $content = str_replace("n.is_read é ", "n.is_read ? ", $content);
    $content = str_replace("totalPedidos != 1 é ", "totalPedidos != 1 ? ", $content);
    $content = str_replace("is_admin é ", "is_admin ? ", $content);
    $content = str_replace("=== 'CLIENT' é ", "=== 'CLIENT' ? ", $content);
    $content = str_replace("=== 'AKSANTI' é ", "=== 'AKSANTI' ? ", $content);
    $content = str_replace("isAtivo é ", "isAtivo ? ", $content);
    $content = str_replace("!isAtivo é ", "!isAtivo ? ", $content);
    $content = str_replace("=== '?' é ", "=== '?' ? ", $content);
    $content = str_replace("=== 'Sem contacto' é ", "=== 'Sem contacto' ? ", $content);
    
    // Fix typo introduced by encoding script
    $content = str_replace("decisóo", "decisão", $content);
    $content = str_replace("açóes", "ações", $content);
    $content = str_replace("Açóes", "Ações", $content);
    
    // Fix double declaration in pedido-detalhe.html
    if ($file === 'pedido-detalhe.html') {
        $doubleDecl = "const ud = JSON.parse(localStorage.getItem('arms_utilizador_dados') || '{}');\r\n                    const isClientRole = ud.tipo === 'CLIENT';\r\n                    const aguardaDecisao";
        $content = str_replace($doubleDecl, "const aguardaDecisao", $content);
        
        $doubleDecl2 = "const ud = JSON.parse(localStorage.getItem('arms_utilizador_dados') || '{}');\n                    const isClientRole = ud.tipo === 'CLIENT';\n                    const aguardaDecisao";
        $content = str_replace($doubleDecl2, "const aguardaDecisao", $content);
    }
    
    file_put_contents($file, $content);
}
echo "Repaired corruptions!";
?>
