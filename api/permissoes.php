<?php
require_once __DIR__ . '/auth.php';

function armsPermissoesCatalogo() {
    return [
        'clientes.ver' => 'Ver clientes',
        'clientes.gerir' => 'Criar e editar clientes',
        'areas.ver' => 'Ver departamentos',
        'areas.gerir' => 'Criar e editar departamentos',
        'pedidos.ver_todos' => 'Ver todos os pedidos',
        'relatorios.exportar' => 'Exportar relatórios',
        'qualidade.ver' => 'Ver qualidade de dados',
        'seguranca.gerir' => 'Gerir segurança automatizada',
        'automacao.gerir' => 'Gerir automação',
        'retencao.gerir' => 'Gerir retenção e auditoria',
    ];
}

function armsPermissoesGarantirTabelas(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS arms.user_permission (
            user_id UUID NOT NULL REFERENCES arms.auth_user(id) ON DELETE CASCADE,
            permission_key VARCHAR(80) NOT NULL,
            granted_by UUID NULL REFERENCES arms.auth_user(id) ON DELETE SET NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            PRIMARY KEY (user_id, permission_key)
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS arms.user_permission_audit (
            id BIGSERIAL PRIMARY KEY,
            user_id UUID NOT NULL REFERENCES arms.auth_user(id) ON DELETE CASCADE,
            permission_key VARCHAR(80) NOT NULL,
            action VARCHAR(16) NOT NULL,
            changed_by UUID NULL REFERENCES arms.auth_user(id) ON DELETE SET NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )
    ");
}

function armsPermissoesNormalizar($permissoes) {
    $catalogo = armsPermissoesCatalogo();
    $entrada = is_array($permissoes) ? $permissoes : [];
    $limpas = [];

    foreach ($entrada as $permissao) {
        $chave = trim((string)$permissao);
        if (isset($catalogo[$chave])) {
            $limpas[$chave] = true;
        }
    }

    return array_keys($limpas);
}

function armsPermissoesDoUtilizador(PDO $pdo, $userId, $isAdmin = false) {
    if (armsAuthBool($isAdmin)) {
        return array_keys(armsPermissoesCatalogo());
    }

    if (!$userId) {
        return [];
    }

    armsPermissoesGarantirTabelas($pdo);

    $stmt = $pdo->prepare("
        SELECT permission_key
        FROM arms.user_permission
        WHERE user_id = :user_id
        ORDER BY permission_key ASC
    ");
    $stmt->execute([':user_id' => $userId]);

    return armsPermissoesNormalizar($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

function armsTemPermissao(PDO $pdo, $permissoes) {
    armsAuthExigirLogin();

    if (armsAuthIsAdmin()) {
        return true;
    }

    $necessarias = is_array($permissoes) ? $permissoes : [$permissoes];
    $atuais = armsPermissoesDoUtilizador($pdo, $_SESSION['arms_user_id'] ?? null, false);

    return (bool)array_intersect($necessarias, $atuais);
}

function armsExigirPermissao(PDO $pdo, $permissoes, $mensagem = 'Não tem permissão para aceder a esta área.') {
    if (!armsTemPermissao($pdo, $permissoes)) {
        echo json_encode(['sucesso' => false, 'erro' => $mensagem]);
        exit;
    }
}

function armsPermissoesSalvarUtilizador(PDO $pdo, $userId, $permissoes, $changedBy = null) {
    armsPermissoesGarantirTabelas($pdo);

    $novas = armsPermissoesNormalizar($permissoes);

    $stmtAtual = $pdo->prepare("SELECT permission_key FROM arms.user_permission WHERE user_id = :user_id");
    $stmtAtual->execute([':user_id' => $userId]);
    $atuais = $stmtAtual->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $adicionar = array_values(array_diff($novas, $atuais));
    $remover = array_values(array_diff($atuais, $novas));

    foreach ($adicionar as $permissao) {
        $stmt = $pdo->prepare("
            INSERT INTO arms.user_permission (user_id, permission_key, granted_by)
            VALUES (:user_id, :permission_key, :granted_by)
            ON CONFLICT (user_id, permission_key) DO NOTHING
        ");
        $stmt->execute([':user_id' => $userId, ':permission_key' => $permissao, ':granted_by' => $changedBy]);

        $audit = $pdo->prepare("
            INSERT INTO arms.user_permission_audit (user_id, permission_key, action, changed_by)
            VALUES (:user_id, :permission_key, 'GRANT', :changed_by)
        ");
        $audit->execute([':user_id' => $userId, ':permission_key' => $permissao, ':changed_by' => $changedBy]);
    }

    foreach ($remover as $permissao) {
        $stmt = $pdo->prepare("DELETE FROM arms.user_permission WHERE user_id = :user_id AND permission_key = :permission_key");
        $stmt->execute([':user_id' => $userId, ':permission_key' => $permissao]);

        $audit = $pdo->prepare("
            INSERT INTO arms.user_permission_audit (user_id, permission_key, action, changed_by)
            VALUES (:user_id, :permission_key, 'REVOKE', :changed_by)
        ");
        $audit->execute([':user_id' => $userId, ':permission_key' => $permissao, ':changed_by' => $changedBy]);
    }
}
?>
