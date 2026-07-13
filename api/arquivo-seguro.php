<?php

function armsArquivoNomeSeguro($nomeOriginal) {
    $nome = basename((string)$nomeOriginal);
    $nome = preg_replace('/[\r\n\t]+/', ' ', $nome);
    $nome = preg_replace('/[^\pL\pN._ -]+/u', '_', $nome);
    $nome = trim($nome, " ._-");

    if ($nome === '') {
        return 'ficheiro';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($nome, 0, 180, 'UTF-8');
    }

    return substr($nome, 0, 180);
}

function armsArquivoExtensao($nomeOriginal) {
    return strtolower(pathinfo((string)$nomeOriginal, PATHINFO_EXTENSION));
}

function armsArquivoExtensaoPermitida($extensao) {
    $bloqueadas = [
        'php', 'phtml', 'phar', 'cgi', 'pl',
        'asp', 'aspx', 'jsp',
        'html', 'htm', 'js', 'svg',
        'exe', 'bat', 'cmd', 'sh', 'ps1', 'msi', 'dll', 'com'
    ];

    return $extensao !== '' && !in_array(strtolower($extensao), $bloqueadas, true);
}

function armsArquivoMimeReal($caminhoTemporario, $mimeInformado = null) {
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $caminhoTemporario);
            finfo_close($finfo);

            if ($mime) {
                return $mime;
            }
        }
    }

    return $mimeInformado ?: 'application/octet-stream';
}

function armsArquivoValidarUpload(array $file) {
    $nomeSeguro = armsArquivoNomeSeguro($file['name'] ?? 'ficheiro');
    $extensao = armsArquivoExtensao($nomeSeguro);

    if (!armsArquivoExtensaoPermitida($extensao)) {
        return [false, 'Este tipo de ficheiro não é permitido por segurança.', null];
    }

    $mimeReal = armsArquivoMimeReal($file['tmp_name'] ?? '', $file['type'] ?? null);

    return [true, null, [
        'nome' => $nomeSeguro,
        'extensao' => $extensao,
        'mime' => $mimeReal
    ]];
}
?>
