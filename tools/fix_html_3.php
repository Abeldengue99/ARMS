<?php
$replacements = [
    'p?r' => 'pôr',
    '?cone' => 'ícone',
    'pain?is' => 'painéis',
    'ret?ngulo' => 'retângulo',
    'for?a' => 'força',
    'r?pidos' => 'rápidos',
    'Prim?ria' => 'Primária',
    'v?o ' => 'vão ',
    'vis?veis' => 'visíveis',
    'v?rias' => 'várias',
    's? ' => 'só ',
    'digit?mos' => 'digitámos',
    'm?gica' => 'mágica'
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
