<?php

function armsGerarSenhaInicial($tamanho = 12) {
    $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $senha = '';
    $limite = strlen($alfabeto) - 1;

    for ($i = 0; $i < $tamanho; $i++) {
        $senha .= $alfabeto[random_int(0, $limite)];
    }

    return $senha;
}

function armsMontarConviteUtilizador($fullName, $email, $senha, $userType, $clienteAssociado = null) {
    $nomeSeguro = armsEmailEscapar($fullName);
    $emailSeguro = armsEmailEscapar($email);
    $senhaSegura = armsEmailEscapar($senha);

    if ($userType === 'CLIENT' && $clienteAssociado) {
        $empresa = $clienteAssociado['name'];
        $nif = $clienteAssociado['tax_id'] ?: 'Não informado';
        $localizacao = $clienteAssociado['location'] ?: 'Não informada';
        $empresaSegura = armsEmailEscapar($empresa);
        $nifSeguro = armsEmailEscapar($nif);
        $localizacaoSegura = armsEmailEscapar($localizacao);

        return [
            'assunto' => 'Convite para gerir a conta da ' . $empresa . ' no ARMS',
            'titulo' => 'Convite para gerir conta de cliente',
            'empresa' => $empresa,
            'nif' => $nif,
            'localizacao' => $localizacao,
            'conteudo_html' => <<<HTML
<p style="margin:0 0 16px;">Olá {$nomeSeguro},</p>
<p style="margin:0 0 18px;">Foi criada uma conta no ARMS para que possa gerir a conta da empresa abaixo.</p>
<div style="background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px; padding:18px; margin:22px 0;">
    <p style="margin:0 0 8px;"><strong>Empresa:</strong> {$empresaSegura}</p>
    <p style="margin:0 0 8px;"><strong>NIF:</strong> {$nifSeguro}</p>
    <p style="margin:0;"><strong>Localização:</strong> {$localizacaoSegura}</p>
</div>
<div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:10px; padding:18px; margin:22px 0;">
    <p style="margin:0 0 8px;"><strong>E-mail de acesso:</strong> {$emailSeguro}</p>
    <p style="margin:0;"><strong>Senha inicial:</strong> {$senhaSegura}</p>
</div>
<p style="margin:18px 0 0; color:#92400e;"><strong>Nota:</strong> se recebeu mais de um convite, use apenas a senha do e-mail mais recente.</p>
<p style="margin:18px 0 0;">Por segurança, recomendamos alterar a senha no primeiro acesso.</p>
HTML
        ];
    }

    return [
        'assunto' => 'Convite para aceder ao ARMS',
        'titulo' => 'Convite para aceder ao ARMS',
        'empresa' => null,
        'nif' => null,
        'localizacao' => null,
        'conteudo_html' => <<<HTML
<p style="margin:0 0 16px;">Olá {$nomeSeguro},</p>
<p style="margin:0 0 18px;">Foi criada uma conta interna no ARMS.</p>
<div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:10px; padding:18px; margin:22px 0;">
    <p style="margin:0 0 8px;"><strong>E-mail de acesso:</strong> {$emailSeguro}</p>
    <p style="margin:0;"><strong>Senha inicial:</strong> {$senhaSegura}</p>
</div>
<p style="margin:18px 0 0; color:#92400e;"><strong>Nota:</strong> se recebeu mais de um convite, use apenas a senha do e-mail mais recente.</p>
<p style="margin:18px 0 0;">Por segurança, recomendamos alterar a senha no primeiro acesso.</p>
HTML
    ];
}
