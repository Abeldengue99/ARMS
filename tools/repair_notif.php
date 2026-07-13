<?php
$file = 'notificacoes.html';
$content = file_get_contents($file);
$content = str_replace("resumo.textContent = naoLidas > 0 \n                        é ", "resumo.textContent = naoLidas > 0 \n                        ? ", $content);
$content = str_replace("resumo.textContent = naoLidas > 0 \r\n                        é ", "resumo.textContent = naoLidas > 0 \r\n                        ? ", $content);

file_put_contents($file, $content);
echo "Fixed notificacoes.html";
?>
