<?php
require_once 'db.php';
require_once 'permissoes.php';

armsAuthIniciarSessao();

header('Content-Type: application/json; charset=utf-8');

function armsEditarUtilizadorBool($valor) {
    return $valor === true || $valor === 1 || $valor === '1' || $valor === 't' || $valor === 'true' || $valor === 'TRUE';
}

function armsEditarUtilizadorTextoTamanho($valor) {
    $texto = (string)$valor;
    return function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen($texto);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido']);
    exit;
}

if (empty($_SESSION['arms_logado']) || !armsEditarUtilizadorBool($_SESSION['arms_is_admin'] ?? false)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Apenas administradores do sistema podem editar utilizadores.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['id'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados inválidos']);
    exit;
}

$id = trim($data['id'] ?? '');
$fullName = trim($data['full_name'] ?? $data['first_name'] ?? '');
$cargo = trim($data['cargo'] ?? $data['last_name'] ?? '');
$email = strtolower(trim($data['email'] ?? ''));
$tipoAcesso = trim($data['tipo_acesso'] ?? 'AKSANTI');
$userType = ($tipoAcesso === 'Cliente' || strtoupper($tipoAcesso) === 'CLIENT') ? 'CLIENT' : 'AKSANTI';
$clientId = trim($data['client_id'] ?? '');
$areaIds = $data['area_ids'] ?? [];
if (!is_array($areaIds)) {
    $areaIds = $data['area_id'] ? [$data['area_id']] : [];
}
$isAdmin = $userType === 'AKSANTI' && armsEditarUtilizadorBool($data['is_admin'] ?? false);
$permissoesExtras = $isAdmin ? [] : armsPermissoesNormalizar($data['permissoes'] ?? []);

if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
    echo json_encode(['sucesso' => false, 'erro' => 'ID de utilizador inválido.']);
    exit;
}

if (empty($fullName) || empty($email)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Nome completo e e-mail são obrigatórios.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Informe um e-mail válido.']);
    exit;
}

if (armsEditarUtilizadorTextoTamanho($fullName) > 160) {
    echo json_encode(['sucesso' => false, 'erro' => 'O nome completo deve ter no máximo 160 caracteres.']);
    exit;
}

if (armsEditarUtilizadorTextoTamanho($cargo) > 160) {
    echo json_encode(['sucesso' => false, 'erro' => 'O cargo deve ter no máximo 160 caracteres.']);
    exit;
}

if ($userType === 'CLIENT' && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $clientId)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Selecione a empresa cliente que este utilizador irá gerir.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmtAtual = $pdo->prepare("
        SELECT id, is_admin, user_type, is_active
        FROM arms.auth_user
        WHERE id = :id
        FOR UPDATE
    ");
    $stmtAtual->execute([':id' => $id]);
    $utilizadorAtual = $stmtAtual->fetch();

    if (!$utilizadorAtual) {
        $pdo->rollBack();
        echo json_encode(['sucesso' => false, 'erro' => 'Utilizador não encontrado.']);
        exit;
    }

    if ($userType === 'CLIENT') {
        $stmtCliente = $pdo->prepare("
            SELECT id
            FROM arms.client
            WHERE id = :id
              AND is_active = TRUE
            FOR SHARE
        ");
        $stmtCliente->execute([':id' => $clientId]);

        if (!$stmtCliente->fetchColumn()) {
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
              AND id <> :id
        ");
        $stmtGestores->execute([':id' => $id]);

        if ((int)$stmtGestores->fetchColumn() >= 3) {
            $pdo->rollBack();
            echo json_encode(['sucesso' => false, 'erro' => 'A Aksanti já tem 3 Super Admins ativos. Desative ou rebaixe um gestor antes de promover outro.']);
            exit;
        }
    }

    if (armsEditarUtilizadorBool($utilizadorAtual['is_admin']) && !$isAdmin && armsEditarUtilizadorBool($utilizadorAtual['is_active'])) {
        $stmtOutrosGestores = $pdo->prepare("
            SELECT COUNT(*)
            FROM arms.auth_user
            WHERE user_type = 'AKSANTI'
              AND is_admin = TRUE
              AND is_active = TRUE
              AND id <> :id
        ");
        $stmtOutrosGestores->execute([':id' => $id]);

        if ((int)$stmtOutrosGestores->fetchColumn() < 1) {
            $pdo->rollBack();
            echo json_encode(['sucesso' => false, 'erro' => 'Não é possível remover o último Super Admin ativo do sistema.']);
            exit;
        }
    }

    $stmtUser = $pdo->prepare("
        UPDATE arms.auth_user
        SET email = :email,
            user_type = :type,
            is_admin = :is_admin
        WHERE id = :id
          AND is_active = TRUE
        RETURNING id
    ");
    $stmtUser->execute([
        ':email' => $email,
        ':type' => $userType,
        ':is_admin' => $isAdmin ? 'true' : 'false',
        ':id' => $id
    ]);

    if (!$stmtUser->fetchColumn()) {
        $pdo->rollBack();
        echo json_encode(['sucesso' => false, 'erro' => 'Utilizador não encontrado ou eliminado.']);
        exit;
    }

    $stmtProfile = $pdo->prepare("
        INSERT INTO arms.user_profile (user_id, full_name, phone, locale)
        VALUES (:user_id, :full_name, :cargo, 'pt-PT')
        ON CONFLICT (user_id) DO UPDATE
        SET full_name = EXCLUDED.full_name,
            phone = EXCLUDED.phone
    ");
    $stmtProfile->execute([
        ':user_id' => $id,
        ':full_name' => $fullName,
        ':cargo' => $cargo ?: null
    ]);

    $stmtLimparContactos = $pdo->prepare("DELETE FROM arms.client_contact WHERE user_id = :user_id");
    $stmtLimparContactos->execute([':user_id' => $id]);

    $stmtLimparAreas = $pdo->prepare("DELETE FROM arms.area_membership WHERE user_id = :user_id");
    $stmtLimparAreas->execute([':user_id' => $id]);

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
            ':user_id' => $id,
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
                ':user_id' => $id,
                ':area_id' => $aId
            ]);
        }
    }

    armsPermissoesSalvarUtilizador($pdo, $id, $permissoesExtras, $_SESSION['arms_user_id'] ?? null);

    $pdo->commit();
    echo json_encode(['sucesso' => true, 'mensagem' => 'Utilizador atualizado com sucesso!']);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e->getCode() == '23505') {
        echo json_encode(['sucesso' => false, 'erro' => 'Já existe um utilizador com este e-mail.']);
    } else {
        error_log('Erro ao atualizar utilizador: ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao atualizar o utilizador: ' . $e->getMessage()]);
    }
}
?>
