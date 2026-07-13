<?php

function armsSenhaNormalizarEntrada($valor) {
    $senha = (string)$valor;
    $senha = str_replace("\xC2\xA0", ' ', $senha);
    $senha = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $senha);

    return trim($senha);
}

?>
