<?php
require_once 'db.php';
require_once 'email.php';
require_once 'utilizador-convite.php';
require_once 'senha-politica.php';
require_once 'permissoes.php';

armsAuthIniciarSessao();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

function armsCriarUtilizadorBool($valor) {
    return $valor === true || $valor === 1 || $valor === '1' || $valor === 't' || $valor === 'true' || $valor === 'TRUE';
}

function armsCriarUtilizadorTextoTamanho($valor) {
    $texto = (string)$valor;
    return function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen($texto);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido']);
    exit;
}

if (empty($_SESSION['arms_logado']) || !armsCriarUtilizadorBool($_SESSION['arms_is_admin'] ?? false)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Apenas Super Admins podem criar utilizadores.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados inválidos']);
    exit;
}

$fullName = trim($data['full_name'] ?? $data['first_name'] ?? '');
$cargo = trim($data['cargo'] ?? $data['last_name'] ?? '');
$email = strtolower(trim($data['email'] ?? ''));
$senha = armsGerarSenhaInicial(12);
$tipoAcesso = trim($data['tipo_acesso'] ?? 'AKSANTI');
$userType = ($tipoAcesso === 'Cliente' || strtoupper($tipoAcesso) === 'CLIENT') ? 'CLIENT' : 'AKSANTI';
$clientId = trim($data['client_id'] ?? '');
$areaIds = $data['area_ids'] ?? [];
if (!is_array($areaIds)) {
    $areaIds = $data['area_id'] ? [$data['area_id']] : [];
}
$isAdmin = $userType === 'AKSANTI' && armsCriarUtilizadorBool($data['is_admin'] ?? false);
$permissoesExtras = $isAdmin ? [] : armsPermissoesNormalizar($data['permissoes'] ?? []);

if (empty($fullName) || empty($email)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Nome completo e e-mail são obrigatórios.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Informe um e-mail válido.']);
    exit;
}

if (armsCriarUtilizadorTextoTamanho($fullName) > 160) {
    echo json_encode(['sucesso' => false, 'erro' => 'O nome completo deve ter no máximo 160 caracteres.']);
    exit;
}

if (armsCriarUtilizadorTextoTamanho($cargo) > 160) {
    echo json_encode(['sucesso' => false, 'erro' => 'O cargo deve ter no máximo 160 caracteres.']);
    exit;
}

if ($userType === 'CLIENT' && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $clientId)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Selecione a empresa cliente que este utilizador irá gerir.']);
    exit;
}

function montarConviteUtilizador($fullName, $email, $senha, $userType, $clienteAssociado = null) {
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
<p style="margin:18px 0 0;">Por segurança, recomendamos alterar a senha no primeiro acesso.</p>
HTML
    ];
}

try {
    armsSenhaPoliticaGarantirEstrutura($pdo);

    $pdo->beginTransaction();

    $clienteAssociado = null;

    if ($userType === 'CLIENT') {
        $stmtCliente = $pdo->prepare("
            SELECT id, name, primary_email, tax_id, location
            FROM arms.client
            WHERE id = :id
              AND is_active = TRUE
            FOR SHARE
        ");
        $stmtCliente->execute([':id' => $clientId]);
        $clienteAssociado = $stmtCliente->fetch();

        if (!$clienteAssociado) {
            $pdo->rollBack();
            echo json_encode(['sucesso' => false, 'erro' => 'Empresa cliente não encontrada ou inativa.']);
            exit;
        }
    }

    if ($isAdmin) {
        $stmtGestores = $pdo->prepare("
            SELECT COUNT(*)
            FROM arms.auth_user
            WHERE user_type = 'AKSANTI'
              AND is_admin = TRUE
              AND is_active = TRUE
              AND email <> :email
        ");
        $stmtGestores->execute([':email' => $email]);

        if ((int)$stmtGestores->fetchColumn() >= 3) {
            $pdo->rollBack();
            echo json_encode(['sucesso' => false, 'erro' => 'A Aksanti já tem 3 Super Admins ativos. Desative ou rebaixe um gestor antes de criar outro.']);
            exit;
        }
    }

    $passwordHash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);

    if (!password_verify($senha, $passwordHash)) {
        $pdo->rollBack();
        echo json_encode(['sucesso' => false, 'erro' => 'Não foi possível validar a senha inicial. Tente novamente.']);
        exit;
    }

    $stmtExistente = $pdo->prepare("
        SELECT id, is_active
        FROM arms.auth_user
        WHERE email = :email
        FOR UPDATE
    ");
    $stmtExistente->execute([':email' => $email]);
    $utilizadorExistente = $stmtExistente->fetch();

    if ($utilizadorExistente && armsCriarUtilizadorBool($utilizadorExistente['is_active'])) {
        $pdo->rollBack();
        echo json_encode(['sucesso' => false, 'erro' => 'Já existe um utilizador com este e-mail.']);
        exit;
    }

    if ($utilizadorExistente) {
        $userId = $utilizadorExistente['id'];
        $stmtUser = $pdo->prepare("
            UPDATE arms.auth_user
            SET password_hash = :password,
                password_changed_at = NOW(),
                user_type = :type,
                is_admin = :is_admin,
                is_active = TRUE
            WHERE id = :id
        ");
        $stmtUser->execute([
            ':password' => $passwordHash,
            ':type' => $userType,
            ':is_admin' => $isAdmin ? 'true' : 'false',
            ':id' => $userId
        ]);
    } else {
        $stmtUser = $pdo->prepare("
            INSERT INTO arms.auth_user (email, password_hash, password_changed_at, user_type, is_admin)
            VALUES (:email, :password, NOW(), :type, :is_admin)
            RETURNING id
        ");

        $stmtUser->execute([
            ':email' => $email,
            ':password' => $passwordHash,
            ':type' => $userType,
            ':is_admin' => $isAdmin ? 'true' : 'false'
        ]);

        $userId = $stmtUser->fetchColumn();
    }

    $stmtProfile = $pdo->prepare("
        INSERT INTO arms.user_profile (user_id, full_name, phone, locale)
        VALUES (:user_id, :full_name, :cargo, 'pt-PT')
        ON CONFLICT (user_id) DO UPDATE
        SET full_name = EXCLUDED.full_name,
            phone = EXCLUDED.phone
    ");

    $stmtProfile->execute([
        ':user_id' => $userId,
        ':full_name' => $fullName,
        ':cargo' => $cargo ?: null
    ]);

    $stmtLimparContactos = $pdo->prepare("DELETE FROM arms.client_contact WHERE user_id = :user_id");
    $stmtLimparContactos->execute([':user_id' => $userId]);

    $stmtLimparAreas = $pdo->prepare("DELETE FROM arms.area_membership WHERE user_id = :user_id");
    $stmtLimparAreas->execute([':user_id' => $userId]);

    if ($userType === 'CLIENT') {
        $stmtContacto = $pdo->prepare("
            INSERT INTO arms.client_contact (client_id, user_id, email, full_name, area_id)
            VALUES (:client_id, :user_id, :email, :full_name, NULL)
            ON CONFLICT (client_id, email) DO UPDATE
            SET user_id = EXCLUDED.user_id,
                full_name = EXCLUDED.full_name,
                area_id = NULL
        ");
        $stmtContacto->execute([
            ':client_id' => $clientId,
            ':user_id' => $userId,
            ':email' => $email,
            ':full_name' => $fullName
        ]);
    }

    if (!$isAdmin && !empty($areaIds)) {
        $stmtArea = $pdo->prepare("
            INSERT INTO arms.area_membership (user_id, area_id, role)
            VALUES (:user_id, :area_id::uuid, 'MEMBER')
            ON CONFLICT DO NOTHING
        ");
        foreach ($areaIds as $aId) {
            if (empty($aId)) continue;
            $stmtArea->execute([
                ':user_id' => $userId,
                ':area_id' => $aId
            ]);
        }
    }

    armsPermissoesSalvarUtilizador($pdo, $userId, $permissoesExtras, $_SESSION['arms_user_id'] ?? null);

    $convite = armsMontarConviteUtilizador($fullName, $email, $senha, $userType, $clienteAssociado);
    error_log('[ARMS] Convite de utilizador preparado para ' . $email . ' (' . $userType . ').');

    $pdo->commit();

    $emailEnviado = false;
    $avisoEmail = null;

    try {
        $emailEnviado = armsEnviarEmail(
            $email,
            $convite['assunto'],
            $convite['titulo'],
            $convite['conteudo_html']
        );
    } catch (Exception $e) {
        $avisoEmail = 'A conta foi criada, mas o convite por e-mail não foi enviado. ' . armsEmailErroAmigavel($e);
        error_log('[ARMS] Falha ao enviar convite Brevo: ' . $e->getMessage());
    }

    echo json_encode([
        'sucesso' => true,
        'mensagem' => $emailEnviado
            ? 'Utilizador criado com sucesso! A senha inicial foi gerada automaticamente e enviada por e-mail.'
            : 'Utilizador criado com sucesso! A senha inicial foi gerada automaticamente.',
        'email_enviado' => $emailEnviado,
        'aviso_email' => $avisoEmail,
        'senha_gerada' => true,
        'convite' => [
            'assunto' => $convite['assunto'],
            'empresa' => $convite['empresa'],
            'nif' => $convite['nif'],
            'localizacao' => $convite['localizacao']
        ]
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e->getCode() == '23505') {
        echo json_encode(['sucesso' => false, 'erro' => 'Já existe um utilizador com este e-mail.']);
    } else {
        error_log('[ARMS] Erro ao criar utilizador: ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao criar o utilizador.']);
    }
}
?>
