<?php
$replacements = [
    'Ã rea' => 'Área',
    'apÃ³s' => 'após',
    'Visóvel' => 'Visível',
    'navegaÃ§Ã£o' => 'navegação',
    'NÃ£o' => 'Não',
    'PÃ¡gina' => 'Página',
    'GestÃ£o' => 'Gestão',
    'AÃ§Ãµes' => 'Ações',
    'Ãºnico' => 'único',
];

$files = glob('*.html');
foreach ($files as $file) {
    $content = file_get_contents($file);
    foreach ($replacements as $old => $new) {
        $content = str_replace($old, $new, $content);
    }
    file_put_contents($file, $content);
}
echo 'Done';
?>
