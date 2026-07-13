<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissoes.php';

function armsPedidosContextoAcesso() {
    armsAuthIniciarSessao();
    $isAdmin = armsAuthBool($_SESSION['arms_is_admin'] ?? false);
    $permissaoVerTodos = false;

    if (!$isAdmin && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO && !empty($_SESSION['arms_user_id'])) {
        $permissaoVerTodos = in_array('pedidos.ver_todos', armsPermissoesDoUtilizador($GLOBALS['pdo'], $_SESSION['arms_user_id'], false), true);
    }

    return [
        'user_id' => $_SESSION['arms_user_id'] ?? null,
        'user_type' => $_SESSION['arms_user_type'] ?? 'AKSANTI',
        'client_id' => $_SESSION['arms_client_id'] ?? null,
        'is_admin' => $isAdmin || $permissaoVerTodos,
    ];
}

function armsPedidosGarantirDestinoInterno(PDO $pdo = null) {
    static $disponivel = null;

    if ($disponivel !== null) {
        return $disponivel;
    }

    if (!$pdo && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $pdo = $GLOBALS['pdo'];
    }

    if (!$pdo) {
        $disponivel = false;
        return false;
    }

    try {
        $pdo->exec("ALTER TABLE arms.request ADD COLUMN IF NOT EXISTS destination_type VARCHAR(16) NOT NULL DEFAULT 'CLIENT'");
        $pdo->exec("ALTER TABLE arms.request ADD COLUMN IF NOT EXISTS recipient_user_id UUID");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_request_destination_type ON arms.request (destination_type)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_request_recipient_user ON arms.request (recipient_user_id)");
        $disponivel = true;
    } catch (Throwable $e) {
        error_log('[ARMS] Nao foi possivel preparar destino interno de pedidos: ' . $e->getMessage());
        $disponivel = false;
    }

    return $disponivel;
}

function armsPedidosGarantirTransicoes(PDO $pdo = null) {
    static $disponivel = null;

    if ($disponivel !== null) {
        return $disponivel;
    }

    if (!$pdo && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $pdo = $GLOBALS['pdo'];
    }

    if (!$pdo) {
        $disponivel = false;
        return false;
    }

    try {
        $pdo->exec(<<<'SQL'
            CREATE OR REPLACE FUNCTION arms.trg_request_valid_transition()
            RETURNS TRIGGER AS $$
            DECLARE
                allowed TEXT[];
            BEGIN
                IF NEW.status IS NOT DISTINCT FROM OLD.status THEN
                    RETURN NEW;
                END IF;

                allowed := CASE OLD.status
                    WHEN 'DRAFT'            THEN ARRAY['SENT']
                    WHEN 'SENT'             THEN ARRAY['RECEIVED']
                    WHEN 'RECEIVED'         THEN ARRAY['CLIENT_RESPONDED']
                    WHEN 'CLIENT_RESPONDED' THEN ARRAY['SENT','ACCEPTED','REJECTED']
                    WHEN 'REJECTED'         THEN ARRAY['CLIENT_RESPONDED']
                    WHEN 'ACCEPTED'         THEN ARRAY['CLOSED']
                    WHEN 'CLOSED'           THEN ARRAY[]::TEXT[]
                    ELSE ARRAY[]::TEXT[]
                END;

                IF NOT (NEW.status = ANY(allowed)) THEN
                    RAISE EXCEPTION 'Invalid transition % -> %', OLD.status, NEW.status
                        USING ERRCODE = 'check_violation';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        $disponivel = true;
    } catch (Throwable $e) {
        error_log('[ARMS] Nao foi possivel atualizar transicoes de pedidos: ' . $e->getMessage());
        $disponivel = false;
    }

    return $disponivel;
}

function armsPedidosFiltroSql($alias = 'r', $prefixo = 'acesso') {
    $ctx = armsPedidosContextoAcesso();
    $temDestinoInterno = armsPedidosGarantirDestinoInterno();

    if ($ctx['is_admin']) {
        return ['', []];
    }

    if ($ctx['user_type'] === 'CLIENT') {
        if (empty($ctx['client_id'])) {
            throw new RuntimeException('Esta conta de cliente não está associada a uma empresa.');
        }

        return [
            " AND {$alias}.client_id = :{$prefixo}_client_id
              AND ({$alias}.status <> 'DRAFT' OR {$alias}.created_by = :{$prefixo}_user_id)",
            [
                ":{$prefixo}_client_id" => $ctx['client_id'],
                ":{$prefixo}_user_id" => $ctx['user_id'],
            ]
        ];
    }

    if (empty($ctx['user_id'])) {
        throw new RuntimeException('Sessão inválida.');
    }

    $filtroDestinatarioInterno = '';
    $paramsDestinatarioInterno = [];
    if ($temDestinoInterno) {
        $filtroDestinatarioInterno = "
            OR (
                {$alias}.recipient_user_id = :{$prefixo}_recipient_user_id
                AND {$alias}.status <> 'DRAFT'
            )";
        $paramsDestinatarioInterno[":{$prefixo}_recipient_user_id"] = $ctx['user_id'];
    }

    return [
        " AND (
            {$alias}.created_by = :{$prefixo}_user_id
            {$filtroDestinatarioInterno}
            OR EXISTS (
                SELECT 1
                FROM arms.request_response {$prefixo}_rr
                WHERE {$prefixo}_rr.request_id = {$alias}.id
                  AND {$prefixo}_rr.responded_by = :{$prefixo}_response_user_id
            )
            OR EXISTS (
                SELECT 1
                FROM arms.notification {$prefixo}_n
                WHERE {$prefixo}_n.request_id = {$alias}.id
                  AND {$prefixo}_n.recipient_id = :{$prefixo}_notification_user_id
            )
        )",
        [
            ":{$prefixo}_user_id" => $ctx['user_id'],
            ":{$prefixo}_response_user_id" => $ctx['user_id'],
            ":{$prefixo}_notification_user_id" => $ctx['user_id'],
        ] + $paramsDestinatarioInterno
    ];
}

function armsPedidosWhereSql($alias = 'r', $prefixo = 'acesso') {
    [$filtro, $params] = armsPedidosFiltroSql($alias, $prefixo);
    return [' WHERE 1=1' . $filtro, $params];
}

function armsPedidosAreaPermitida(PDO $pdo, $areaId) {
    $ctx = armsPedidosContextoAcesso();

    if ($ctx['is_admin'] || $ctx['user_type'] === 'CLIENT') {
        return true;
    }

    if (empty($ctx['user_id']) || empty($areaId)) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT 1
        FROM arms.area_membership
        WHERE user_id = :user_id
          AND area_id = :area_id
        LIMIT 1
    ");
    $stmt->execute([
        ':user_id' => $ctx['user_id'],
        ':area_id' => $areaId,
    ]);

    return (bool)$stmt->fetchColumn();
}

function armsPedidosClienteInternoAksanti(PDO $pdo) {
    $email = 'admin@aksanti.xyz';

    $stmt = $pdo->prepare("
        SELECT id, name, primary_email
        FROM arms.client
        WHERE lower(name) = lower('Aksanti')
           OR primary_email = :email
        ORDER BY lower(name) = lower('Aksanti') DESC, created_at ASC
        LIMIT 1
    ");
    $stmt->execute([':email' => $email]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cliente) {
        return $cliente;
    }

    $stmt = $pdo->prepare("
        INSERT INTO arms.client (name, primary_email, is_active)
        VALUES ('Aksanti', :email, TRUE)
        RETURNING id, name, primary_email
    ");
    $stmt->execute([':email' => $email]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
