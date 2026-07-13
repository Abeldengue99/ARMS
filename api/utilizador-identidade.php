<?php

function armsPrimeiroCaractere($texto) {
    $texto = trim((string)$texto);

    if ($texto === '') {
        return '';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($texto, 0, 1, 'UTF-8');
    }

    if (preg_match('/^./u', $texto, $match)) {
        return $match[0];
    }

    return substr($texto, 0, 1);
}

function armsMaiusculas($texto) {
    $texto = (string)$texto;

    if (function_exists('mb_strtoupper')) {
        return mb_strtoupper($texto, 'UTF-8');
    }

    return strtoupper($texto);
}

function armsIniciaisUtilizador($nome, $email = '') {
    $base = trim((string)$nome);

    if ($base === '' || strcasecmp($base, 'Utilizador') === 0) {
        $base = trim((string)$email);
    }

    if ($base === '') {
        return 'U';
    }

    if (strpos($base, '@') !== false) {
        $base = substr($base, 0, strpos($base, '@'));
    }

    $base = preg_replace('/\s*\([^)]*\)\s*/u', ' ', $base);
    $partes = preg_split('/[\s._-]+/u', $base, -1, PREG_SPLIT_NO_EMPTY);

    if (!$partes) {
        return 'U';
    }

    $primeira = armsPrimeiroCaractere($partes[0]);
    $ultima = count($partes) > 1 ? armsPrimeiroCaractere($partes[count($partes) - 1]) : '';

    $iniciais = armsMaiusculas($primeira . $ultima);

    return $iniciais !== '' ? $iniciais : 'U';
}

?>
