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
        $pdo->exec("ALTER TABLE arms.client_contact ADD COLUMN IF NOT EXISTS area_id UUID REFERENCES arms.area(id) ON DELETE SET NULL");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_request_destination_type ON arms.request (destination_type)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_request_recipient_user ON arms.request (recipient_user_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_client_contact_area ON arms.client_contact (client_id, area_id)");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS arms.request_recipient (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                request_id UUID NOT NULL REFERENCES arms.request(id) ON DELETE CASCADE,
                user_id UUID NOT NULL REFERENCES arms.auth_user(id) ON DELETE CASCADE,
                client_id UUID NULL REFERENCES arms.client(id) ON DELETE CASCADE,
                area_id UUID NULL REFERENCES arms.area(id) ON DELETE SET NULL,
                recipient_type VARCHAR(20) NOT NULL DEFAULT 'USER',
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                received_at TIMESTAMPTZ NULL,
                viewed_at TIMESTAMPTZ NULL,
                responded_at TIMESTAMPTZ NULL,
                CONSTRAINT uq_request_recipient_user UNIQUE (request_id, user_id)
            )
        ");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_request_recipient_request ON arms.request_recipient (request_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_request_recipient_user ON arms.request_recipient (user_id)");
        $disponivel = true;
    } catch (Throwable $e) {
        error_log('[ARMS] Nao foi possivel preparar destino interno de pedidos: ' . $e->getMessage());
        $disponivel = false;
    }

    return $disponivel;
}

function armsPedidosNormalizarAreas(?string $areaId, array $areaIds = []) {
    $ids = [];
    foreach (array_merge([$areaId], $areaIds) as $id) {
        $id = trim((string)$id);
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
            $ids[strtolower($id)] = $id;
        }
    }

    return array_values($ids);
}

function armsPedidosTemDestinatarios(PDO $pdo, string $requestId) {
    armsPedidosGarantirDestinoInterno($pdo);

    $stmt = $pdo->prepare("
        SELECT 1
        FROM arms.request_recipient
        WHERE request_id = :request_id
        LIMIT 1
    ");
    $stmt->execute([':request_id' => $requestId]);

    return (bool)$stmt->fetchColumn();
}

function armsPedidosUtilizadorEDestinatario(PDO $pdo, ?string $requestId, ?string $userId) {
    if (!$requestId || !$userId) {
        return false;
    }

    armsPedidosGarantirDestinoInterno($pdo);

    $stmt = $pdo->prepare("
        SELECT 1
        FROM arms.request_recipient
        WHERE request_id = :request_id
          AND user_id = :user_id
        LIMIT 1
    ");
    $stmt->execute([
        ':request_id' => $requestId,
        ':user_id' => $userId,
    ]);

    return (bool)$stmt->fetchColumn();
}

function armsPedidosDestinatariosIds(PDO $pdo, string $requestId) {
    armsPedidosGarantirDestinoInterno($pdo);

    $stmt = $pdo->prepare("
        SELECT user_id
        FROM arms.request_recipient
        WHERE request_id = :request_id
    ");
    $stmt->execute([':request_id' => $requestId]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function armsPedidosObterDestinatarioAtual(PDO $pdo, ?string $requestId, ?string $userId) {
    if (!$requestId || !$userId) {
        return null;
    }

    armsPedidosGarantirDestinoInterno($pdo);

    $stmt = $pdo->prepare("
        SELECT
            rr.id,
            rr.request_id,
            rr.user_id,
            rr.client_id,
            rr.area_id,
            rr.recipient_type,
            to_char(rr.created_at, 'YYYY-MM-DD HH24:MI') as created_at,
            to_char(rr.received_at, 'YYYY-MM-DD HH24:MI') as received_at,
            to_char(rr.viewed_at, 'YYYY-MM-DD HH24:MI') as viewed_at,
            to_char(rr.responded_at, 'YYYY-MM-DD HH24:MI') as responded_at,
            COALESCE(up.full_name, au.email, rr.user_id::text) as recipient_name,
            COALESCE(au.user_type, '') as recipient_user_type
        FROM arms.request_recipient rr
        LEFT JOIN arms.user_profile up ON rr.user_id = up.user_id
        LEFT JOIN arms.auth_user au ON rr.user_id = au.id
        WHERE rr.request_id = :request_id
          AND rr.user_id = :user_id
        LIMIT 1
    ");
    $stmt->execute([
        ':request_id' => $requestId,
        ':user_id' => $userId,
    ]);

    $linha = $stmt->fetch(PDO::FETCH_ASSOC);
    return $linha ?: null;
}

function armsPedidosMarcarDestinatarioVisualizado(PDO $pdo, string $requestId, string $userId) {
    armsPedidosGarantirDestinoInterno($pdo);

    $stmt = $pdo->prepare("
        UPDATE arms.request_recipient
        SET received_at = COALESCE(received_at, NOW()),
            viewed_at = COALESCE(viewed_at, NOW())
        WHERE request_id = :request_id
          AND user_id = :user_id
    ");
    $stmt->execute([
        ':request_id' => $requestId,
        ':user_id' => $userId,
    ]);
}

function armsPedidosMarcarDestinatarioRespondido(PDO $pdo, string $requestId, string $userId) {
    armsPedidosGarantirDestinoInterno($pdo);

    $stmt = $pdo->prepare("
        UPDATE arms.request_recipient
        SET received_at = COALESCE(received_at, NOW()),
            viewed_at = COALESCE(viewed_at, NOW()),
            responded_at = COALESCE(responded_at, NOW())
        WHERE request_id = :request_id
          AND user_id = :user_id
    ");
    $stmt->execute([
        ':request_id' => $requestId,
        ':user_id' => $userId,
    ]);
}

function armsPedidosReiniciarRastreioDestinatarios(PDO $pdo, string $requestId) {
    armsPedidosGarantirDestinoInterno($pdo);

    $stmt = $pdo->prepare("
        UPDATE arms.request_recipient
        SET received_at = NULL,
            viewed_at = NULL,
            responded_at = NULL
        WHERE request_id = :request_id
    ");
    $stmt->execute([':request_id' => $requestId]);
}

function armsPedidosResumoDestinatarios(PDO $pdo, string $requestId) {
    armsPedidosGarantirDestinoInterno($pdo);

    $stmt = $pdo->prepare("
        SELECT
            rr.user_id,
            rr.recipient_type,
            rr.received_at,
            rr.viewed_at,
            rr.responded_at,
            COALESCE(up.full_name, au.email, rr.user_id::text) as recipient_name
        FROM arms.request_recipient rr
        LEFT JOIN arms.user_profile up ON rr.user_id = up.user_id
        LEFT JOIN arms.auth_user au ON rr.user_id = au.id
        WHERE rr.request_id = :request_id
        ORDER BY COALESCE(up.full_name, au.email, rr.user_id::text) ASC
    ");
    $stmt->execute([':request_id' => $requestId]);

    $destinatarios = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $total = count($destinatarios);
    $recebidos = 0;
    $vistos = 0;
    $respondidos = 0;
    $nomesFaltam = [];
    $nomesRecebidos = [];
    $nomesVistos = [];
    $nomesRespondidos = [];
    $ultimoRespondido = null;

    foreach ($destinatarios as $destinatario) {
        $nome = trim((string)($destinatario['recipient_name'] ?? ''));
        if ($nome === '') {
            $nome = (string)($destinatario['user_id'] ?? 'Desconhecido');
        }

        $temRecebido = !empty($destinatario['received_at']);
        $temVisto = !empty($destinatario['viewed_at']);
        $temResposta = !empty($destinatario['responded_at']);

        if ($temRecebido) {
            $recebidos++;
            $nomesRecebidos[] = $nome;
        } else {
            $nomesFaltam[] = $nome;
        }

        if ($temVisto) {
            $vistos++;
            $nomesVistos[] = $nome;
        }

        if ($temResposta) {
            $respondidos++;
            $nomesRespondidos[] = $nome;

            if (!$ultimoRespondido || strtotime((string)$destinatario['responded_at']) > strtotime((string)($ultimoRespondido['responded_at'] ?? '1970-01-01 00:00'))) {
                $ultimoRespondido = [
                    'nome' => $nome,
                    'responded_at' => $destinatario['responded_at'],
                    'recipient_type' => $destinatario['recipient_type'] ?? null,
                    'user_id' => $destinatario['user_id'] ?? null,
                ];
            }
        }
    }

    return [
        'has_recipients' => $total > 0,
        'total' => $total,
        'received_count' => $recebidos,
        'viewed_count' => $vistos,
        'responded_count' => $respondidos,
        'all_received' => $total > 0 && $recebidos === $total,
        'all_viewed' => $total > 0 && $vistos === $total,
        'missing_names' => array_values(array_unique($nomesFaltam)),
        'received_names' => array_values(array_unique($nomesRecebidos)),
        'viewed_names' => array_values(array_unique($nomesVistos)),
        'responded_names' => array_values(array_unique($nomesRespondidos)),
        'last_response_by_name' => $ultimoRespondido['nome'] ?? null,
        'last_response_at' => $ultimoRespondido['responded_at'] ?? null,
        'last_response_type' => $ultimoRespondido['recipient_type'] ?? null,
        'last_response_user_id' => $ultimoRespondido['user_id'] ?? null,
    ];
}

function armsPedidosRegistrarDestinatarios(PDO $pdo, string $requestId, string $destinationType, ?string $clientId, ?string $areaId, string $createdBy, ?string $recipientUserId = null, array $areaIds = []) {
    armsPedidosGarantirDestinoInterno($pdo);

    $areas = armsPedidosNormalizarAreas($areaId, $areaIds);
    if (!$areas) {
        $areas = [$areaId];
    }

    $stmtLimpar = $pdo->prepare("DELETE FROM arms.request_recipient WHERE request_id = :request_id");
    $stmtLimpar->execute([':request_id' => $requestId]);

    if (strtoupper((string)$destinationType) === 'AKSANTI') {
        if ($recipientUserId) {
            $stmt = $pdo->prepare("
                INSERT INTO arms.request_recipient (request_id, user_id, area_id, recipient_type)
                SELECT :request_id::uuid, au.id, :area_id::uuid, 'USER'
                FROM arms.auth_user au
                WHERE au.id = :user_id::uuid
                  AND au.user_type = 'AKSANTI'
                  AND au.is_active = TRUE
                ON CONFLICT (request_id, user_id) DO NOTHING
            ");
            $stmt->execute([
                ':request_id' => $requestId,
                ':user_id' => $recipientUserId,
                ':area_id' => $areaId,
            ]);
        } else {
            $placeholders = [];
            $params = [
                ':request_id' => $requestId,
                ':created_by' => $createdBy,
            ];
            foreach ($areas as $index => $id) {
                $chave = ':area_' . $index;
                $placeholders[] = $chave . '::uuid';
                $params[$chave] = $id;
            }

            $stmt = $pdo->prepare("
                INSERT INTO arms.request_recipient (request_id, user_id, area_id, recipient_type)
                SELECT DISTINCT :request_id::uuid, au.id, am.area_id, 'DEPARTMENT'
                FROM arms.auth_user au
                INNER JOIN arms.area_membership am ON am.user_id = au.id
                WHERE au.is_active = TRUE
                  AND au.user_type = 'AKSANTI'
                  AND au.id <> :created_by::uuid
                  AND am.area_id IN (" . implode(',', $placeholders) . ")
                ON CONFLICT (request_id, user_id) DO NOTHING
            ");
            $stmt->execute($params);
        }
    } else {
        $placeholders = [];
        $params = [
            ':request_id' => $requestId,
            ':client_id' => $clientId,
        ];
        foreach ($areas as $index => $id) {
            $chave = ':client_area_' . $index;
            $placeholders[] = $chave . '::uuid';
            $params[$chave] = $id;
        }

        $stmt = $pdo->prepare("
            INSERT INTO arms.request_recipient (request_id, user_id, client_id, area_id, recipient_type)
            SELECT DISTINCT :request_id::uuid, cc.user_id, cc.client_id, am.area_id, 'DEPARTMENT'
            FROM arms.client_contact cc
            INNER JOIN arms.auth_user au ON au.id = cc.user_id
            INNER JOIN arms.area_membership am ON am.user_id = au.id
            WHERE cc.client_id = :client_id::uuid
              AND cc.user_id IS NOT NULL
              AND au.is_active = TRUE
              AND am.area_id IN (" . implode(',', $placeholders) . ")
            ON CONFLICT (request_id, user_id) DO NOTHING
        ");
        $stmt->execute($params);
    }

    return armsPedidosDestinatariosIds($pdo, $requestId);
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
                    WHEN 'SENT'             THEN ARRAY['RECEIVED', 'CLOSED']
                    WHEN 'RECEIVED'         THEN ARRAY['CLIENT_RESPONDED', 'CLOSED']
                    WHEN 'CLIENT_RESPONDED' THEN ARRAY['SENT','ACCEPTED','REJECTED', 'CLOSED']
                    WHEN 'REJECTED'         THEN ARRAY['CLIENT_RESPONDED', 'CLOSED']
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
        return [
            " AND ({$alias}.status <> 'DRAFT' OR {$alias}.created_by = :{$prefixo}_admin_user_id)",
            [":{$prefixo}_admin_user_id" => $ctx['user_id']]
        ];
    }

    if ($ctx['user_type'] === 'CLIENT') {
        if (empty($ctx['client_id'])) {
            throw new RuntimeException('Esta conta de cliente não está associada a uma empresa.');
        }

        return [
            " AND (
                {$alias}.created_by = :{$prefixo}_user_id
                OR EXISTS (
                    SELECT 1
                    FROM arms.request_recipient {$prefixo}_rec
                    WHERE {$prefixo}_rec.request_id = {$alias}.id
                      AND {$prefixo}_rec.user_id = :{$prefixo}_recipient_user_id
                      AND {$alias}.status <> 'DRAFT'
                )
                OR (
                    NOT EXISTS (
                        SELECT 1
                        FROM arms.request_recipient {$prefixo}_rec_old
                        WHERE {$prefixo}_rec_old.request_id = {$alias}.id
                    )
                    AND {$alias}.client_id = :{$prefixo}_client_id
                    AND ({$alias}.status <> 'DRAFT' OR {$alias}.created_by = :{$prefixo}_user_id)
                    AND (
                        NOT EXISTS (
                            SELECT 1 FROM arms.area_membership {$prefixo}_am
                            WHERE {$prefixo}_am.user_id = :{$prefixo}_user_id 
                        )
                        OR EXISTS (
                            SELECT 1 FROM arms.area_membership {$prefixo}_am
                            WHERE {$prefixo}_am.user_id = :{$prefixo}_user_id 
                              AND {$prefixo}_am.area_id = {$alias}.area_id
                        )
                    )
                )
            )",
            [
                ":{$prefixo}_client_id" => $ctx['client_id'],
                ":{$prefixo}_user_id" => $ctx['user_id'],
                ":{$prefixo}_recipient_user_id" => $ctx['user_id'],
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
            )
            OR EXISTS (
                SELECT 1
                FROM arms.request_recipient {$prefixo}_rec
                WHERE {$prefixo}_rec.request_id = {$alias}.id
                  AND {$prefixo}_rec.user_id = :{$prefixo}_request_recipient_user_id
                  AND {$alias}.status <> 'DRAFT'
            )";
        $paramsDestinatarioInterno[":{$prefixo}_recipient_user_id"] = $ctx['user_id'];
        $paramsDestinatarioInterno[":{$prefixo}_request_recipient_user_id"] = $ctx['user_id'];
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

function armsPedidosAreaPermitida(PDO $pdo, ?string $areaId) {
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
    $email = 'geral@aksanti.xyz';

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
