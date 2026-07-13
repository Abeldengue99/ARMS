<?php
/**
 * Serviço simples de e-mail transacional via SMTP Brevo.
 */

function armsEmailConfig() {
    return [
        'host' => getenv('ARMS_SMTP_HOST') ?: 'smtp-relay.brevo.com',
        'port' => (int)(getenv('ARMS_SMTP_PORT') ?: 587),
        'user' => getenv('ARMS_SMTP_USER') ?: 'a2ea36001@smtp-brevo.com',
        'pass' => getenv('ARMS_SMTP_PASS') ?: 'MzLy1NkP7DxvOIYg',
        'from_email' => getenv('ARMS_MAIL_FROM') ?: 'tabelaabel99@gmail.com',
        'from_name' => getenv('ARMS_MAIL_FROM_NAME') ?: 'Aksanti Request Management System',
        'timeout' => (int)(getenv('ARMS_SMTP_TIMEOUT') ?: 20)
    ];
}

function armsEmailEscapar($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function armsEmailCabecalho($valor) {
    $valor = trim(preg_replace('/[\r\n]+/', ' ', (string)$valor));
    return '=?UTF-8?B?' . base64_encode($valor) . '?=';
}

function armsEmailTemplate($titulo, $conteudoHtml) {
    $ano = date('Y');
    $tituloSeguro = armsEmailEscapar($titulo);

    return <<<HTML
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>{$tituloSeguro}</title>
</head>
<body style="margin:0; padding:0; background:#f4f6f8; font-family:Segoe UI, Arial, sans-serif; color:#27272a;">
    <div style="max-width:640px; margin:32px auto; background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden;">
        <div style="background:#111111; padding:28px 32px; text-align:left;">
            <h1 style="margin:0; color:#ffffff; font-size:22px; line-height:1.3;">ARMS</h1>
            <p style="margin:6px 0 0; color:#e5a547; font-size:14px;">Aksanti Request Management System</p>
        </div>
        <div style="padding:34px 32px;">
            <h2 style="margin:0 0 18px; color:#18181b; font-size:22px; line-height:1.3;">{$tituloSeguro}</h2>
            {$conteudoHtml}
        </div>
        <div style="background:#f8fafc; padding:20px 32px; color:#71717a; font-size:12px; line-height:1.6;">
            &copy; {$ano} Aksanti. Todos os direitos reservados.<br>
            Esta mensagem foi enviada automaticamente pelo ARMS.
        </div>
    </div>
</body>
</html>
HTML;
}

function armsSmtpLerResposta($socket) {
    $resposta = '';

    while (($linha = fgets($socket, 515)) !== false) {
        $resposta .= $linha;

        if (preg_match('/^\d{3} /', $linha)) {
            break;
        }
    }

    return $resposta;
}

function armsSmtpCodigo($resposta) {
    return (int)substr(trim($resposta), 0, 3);
}

function armsSmtpEsperar($socket, array $codigosEsperados, $contexto) {
    $resposta = armsSmtpLerResposta($socket);
    $codigo = armsSmtpCodigo($resposta);

    if (!in_array($codigo, $codigosEsperados, true)) {
        throw new Exception($contexto . ': ' . trim($resposta));
    }

    return $resposta;
}

function armsSmtpComando($socket, $comando, array $codigosEsperados, $contexto) {
    fwrite($socket, $comando . "\r\n");
    return armsSmtpEsperar($socket, $codigosEsperados, $contexto);
}

function armsEmailErroAmigavel($erro) {
    $mensagem = $erro instanceof Throwable ? $erro->getMessage() : (string)$erro;

    if (stripos($mensagem, 'Unauthorized IP address') !== false || stripos($mensagem, '525 5.7.1') !== false) {
        return 'A Brevo recusou o envio porque o IP deste servidor não está autorizado nas configurações SMTP. Autorize o IP atual na Brevo ou remova a restrição de IP da chave SMTP.';
    }

    if (stripos($mensagem, 'Senha SMTP recusada') !== false || stripos($mensagem, 'Authentication') !== false || stripos($mensagem, 'AUTH') !== false) {
        return 'A Brevo recusou as credenciais SMTP. Verifique o utilizador SMTP, a chave SMTP e se a chave está ativa.';
    }

    if (stripos($mensagem, 'Remetente recusado') !== false || stripos($mensagem, 'MAIL FROM') !== false) {
        return 'A Brevo recusou o remetente. Confirme se o e-mail remetente está validado/autorizado na conta Brevo.';
    }

    if (stripos($mensagem, 'Não foi possível ligar ao SMTP') !== false || stripos($mensagem, 'Connection') !== false) {
        return 'Não foi possível ligar ao servidor SMTP da Brevo. Verifique a internet do servidor, firewall, host e porta SMTP.';
    }

    return 'Não foi possível enviar o e-mail neste momento. Verifique as configurações SMTP da Brevo.';
}

function armsSmtpPrepararMensagem($mensagem) {
    $mensagem = str_replace(["\r\n", "\r"], "\n", $mensagem);
    $linhas = explode("\n", $mensagem);

    foreach ($linhas as &$linha) {
        if (isset($linha[0]) && $linha[0] === '.') {
            $linha = '.' . $linha;
        }
    }

    return implode("\r\n", $linhas);
}

function armsEnviarEmail($destinatario, $assunto, $titulo, $conteudoHtml) {
    $config = armsEmailConfig();
    $destinatario = trim((string)$destinatario);

    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Destinatário de e-mail inválido.');
    }

    $fromEmail = trim($config['from_email']);
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Remetente de e-mail inválido.');
    }

    $html = armsEmailTemplate($titulo, $conteudoHtml);
    $assuntoSeguro = armsEmailCabecalho($assunto);
    $fromNomeSeguro = armsEmailCabecalho($config['from_name']);
    $messageId = sprintf('<%s@arms.local>', bin2hex(random_bytes(16)));

    $mensagem = implode("\r\n", [
        'From: ' . $fromNomeSeguro . ' <' . $fromEmail . '>',
        'To: <' . $destinatario . '>',
        'Subject: ' . $assuntoSeguro,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'Message-ID: ' . $messageId,
        'Date: ' . date(DATE_RFC2822),
        '',
        $html
    ]);

    $errno = 0;
    $errstr = '';
    $socket = stream_socket_client(
        'tcp://' . $config['host'] . ':' . $config['port'],
        $errno,
        $errstr,
        $config['timeout'],
        STREAM_CLIENT_CONNECT
    );

    if (!$socket) {
        throw new Exception('Não foi possível ligar ao SMTP Brevo: ' . $errstr);
    }

    stream_set_timeout($socket, $config['timeout']);

    try {
        armsSmtpEsperar($socket, [220], 'Saudação SMTP inválida');
        armsSmtpComando($socket, 'EHLO arms.local', [250], 'EHLO inicial recusado');
        armsSmtpComando($socket, 'STARTTLS', [220], 'STARTTLS recusado');

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception('Não foi possível ativar TLS no SMTP.');
        }

        armsSmtpComando($socket, 'EHLO arms.local', [250], 'EHLO pós-TLS recusado');
        armsSmtpComando($socket, 'AUTH LOGIN', [334], 'AUTH LOGIN recusado');
        armsSmtpComando($socket, base64_encode($config['user']), [334], 'Utilizador SMTP recusado');
        armsSmtpComando($socket, base64_encode($config['pass']), [235], 'Senha SMTP recusada');
        armsSmtpComando($socket, 'MAIL FROM:<' . $fromEmail . '>', [250], 'Remetente recusado');
        armsSmtpComando($socket, 'RCPT TO:<' . $destinatario . '>', [250, 251], 'Destinatário recusado');
        armsSmtpComando($socket, 'DATA', [354], 'DATA recusado');
        fwrite($socket, armsSmtpPrepararMensagem($mensagem) . "\r\n.\r\n");
        armsSmtpEsperar($socket, [250], 'Mensagem recusada');
        armsSmtpComando($socket, 'QUIT', [221], 'QUIT recusado');
    } finally {
        fclose($socket);
    }

    return true;
}
?>
