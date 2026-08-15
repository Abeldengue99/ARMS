<?php
require_once 'db.php';
require_once 'auth.php';
require_once 'acesso-pedidos.php';
require_once 'configuracoes-servico.php';
require_once 'email.php';
require_once 'utilizador-convite.php';
require_once 'senha-politica.php';
require_once 'permissoes.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function armsAutomacaoBool($valor) {
    return $valor === true || $valor === 1 || $valor === '1' || $valor === 't' || $valor === 'true' || $valor === 'TRUE';
}

function armsAutomacaoInt($valor, $padrao, $minimo, $maximo) {
    $numero = filter_var($valor, FILTER_VALIDATE_INT);
    if ($numero === false) return $padrao;
    return max($minimo, min($maximo, (int)$numero));
}

function armsAutomacaoContar(PDO $pdo, $sql, array $params = []) {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $chave => $valor) {
        $stmt->bindValue($chave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function armsAutomacaoConfiguracoes(PDO $pdo) {
    armsConfiguracoesGarantirEstrutura($pdo);

    return [
        'invite_resend_enabled' => armsConfiguracaoInteiro($pdo, 'automation_invite_resend_enabled', 0, 0, 1) === 1,
        'invite_resend_days' => armsConfiguracaoInteiro($pdo, 'automation_invite_resend_days', 2, 1, 90),
        'deadline_warning_enabled' => armsConfiguracaoInteiro($pdo, 'automation_deadline_warning_enabled', 1, 0, 1) === 1,
        'deadline_warning_hours' => armsConfiguracaoInteiro($pdo, 'automation_deadline_warning_hours', 72, 1, 168),
        'deadline_overdue_enabled' => armsConfiguracaoInteiro($pdo, 'automation_deadline_overdue_enabled', 1, 0, 1) === 1,
        'retention_cleanup_enabled' => armsConfiguracaoInteiro($pdo, 'automation_retention_cleanup_enabled', 0, 0, 1) === 1,
        'attachment_max_size_mb' => armsConfiguracaoInteiro($pdo, 'attachment_max_size_mb', 50, 1, 10240),
    ];
}

function armsAutomacaoResumo(PDO $pdo) {
    $cfg = armsAutomacaoConfiguracoes($pdo);
    $diasConvite = (int)$cfg['invite_resend_days'];
    $horasDeadline = (int)$cfg['deadline_warning_hours'];
    $limiteBytes = (int)$cfg['attachment_max_size_mb'] * 1024 * 1024;

    $convitesPendentes = armsAutomacaoContar($pdo, "
        SELECT COUNT(*)
        FROM arms.auth_user
        WHERE is_active = TRUE
          AND last_login_at IS NULL
    ");

    $convitesElegiveis = armsAutomacaoContar($pdo, "
        SELECT COUNT(*)
        FROM arms.auth_user
        WHERE is_active = TRUE
          AND last_login_at IS NULL
          AND created_at <= NOW() - ((:dias)::int * INTERVAL '1 day')
    ", [':dias' => $diasConvite]);

    $deadlinesVencidos = armsAutomacaoContar($pdo, "
        SELECT COUNT(*)
        FROM arms.request
        WHERE status IN ('SENT', 'RECEIVED')
          AND deadline_at < NOW()
    ");

    $deadlinesProximos = armsAutomacaoContar($pdo, "
        SELECT COUNT(*)
        FROM arms.request
        WHERE status IN ('SENT', 'RECEIVED')
          AND deadline_at >= NOW()
          AND deadline_at <= NOW() + ((:horas)::int * INTERVAL '1 hour')
    ", [':horas' => $horasDeadline]);

    $clientesIncompletos = armsAutomacaoContar($pdo, "
        SELECT COUNT(*)
        FROM arms.client c
        WHERE c.is_active = TRUE
          AND (
              TRIM(COALESCE(c.tax_id::text, '')) = ''
              OR TRIM(COALESCE(c.location, '')) = ''
              OR TRIM(COALESCE(c.primary_email::text, '')) = ''
              OR NOT EXISTS (
                  SELECT 1
                  FROM arms.client_contact cc
                  WHERE cc.client_id = c.id
              )
          )
    ");

    $anexosAcimaLimite = armsAutomacaoContar($pdo, "
        SELECT COUNT(*)
        FROM arms.attachment
        WHERE COALESCE(size_bytes, 0) > :limite
    ", [':limite' => $limiteBytes]);

    $acoesProntas = 0;
    if ($cfg['invite_resend_enabled']) $acoesProntas += $convitesElegiveis;
    if ($cfg['deadline_overdue_enabled']) $acoesProntas += $deadlinesVencidos;
    if ($cfg['deadline_warning_enabled']) $acoesProntas += $deadlinesProximos;

    return [
        'configuracoes' => $cfg,
        'resumo' => [
            'pendencias_total' => $convitesPendentes + $deadlinesVencidos + $deadlinesProximos + $clientesIncompletos + $anexosAcimaLimite,
            'acoes_prontas' => $acoesProntas,
            'convites_pendentes' => $convitesPendentes,
            'convites_elegiveis' => $convitesElegiveis,
            'deadlines_vencidos' => $deadlinesVencidos,
            'deadlines_proximos' => $deadlinesProximos,
            'clientes_incompletos' => $clientesIncompletos,
            'anexos_acima_limite' => $anexosAcimaLimite,
        ],
        'candidatos' => [
            ['chave' => 'convites_pendentes', 'nome' => 'Convites pendentes', 'quantidade' => $convitesElegiveis, 'estado' => $cfg['invite_resend_enabled'] ? 'Automacao ativa' : 'Desativado', 'acao' => $cfg['invite_resend_enabled'] ? 'Reenvia convites elegiveis com nova senha inicial.' : 'Ative a regra para reenvio automatico.'],
            ['chave' => 'deadlines_vencidos', 'nome' => 'Pedidos com deadline vencido', 'quantidade' => $deadlinesVencidos, 'estado' => $cfg['deadline_overdue_enabled'] ? 'Automacao ativa' : 'Desativado', 'acao' => 'Cria notificacoes urgentes para clientes e equipa interna.'],
            ['chave' => 'deadlines_proximos', 'nome' => 'Pedidos proximos do prazo', 'quantidade' => $deadlinesProximos, 'estado' => $cfg['deadline_warning_enabled'] ? 'Automacao ativa' : 'Desativado', 'acao' => 'Cria alerta preventivo antes da data limite.'],
            ['chave' => 'clientes_incompletos', 'nome' => 'Clientes com dados incompletos', 'quantidade' => $clientesIncompletos, 'estado' => 'Revisao', 'acao' => 'Atualize NIF, localizacao, e-mail principal e contacto.'],
            ['chave' => 'anexos_acima_limite', 'nome' => 'Anexos acima do limite atual', 'quantidade' => $anexosAcimaLimite, 'estado' => 'Revisao', 'acao' => 'Verifique documentos que ultrapassam o limite definido.'],
        ],
        'ultima_execucao' => [
            'data' => armsConfiguracaoObter($pdo, 'automation_last_run_at', ''),
            'resumo' => armsConfiguracaoObter($pdo, 'automation_last_run_summary', ''),
        ],
    ];
}

function armsAutomacaoSalvar(PDO $pdo, array $entrada, $executedBy = null) {
    $cfg = [
        'automation_invite_resend_enabled' => armsAutomacaoBool($entrada['invite_resend_enabled'] ?? false) ? 1 : 0,
        'automation_invite_resend_days' => armsAutomacaoInt($entrada['invite_resend_days'] ?? 2, 2, 1, 90),
        'automation_deadline_warning_enabled' => armsAutomacaoBool($entrada['deadline_warning_enabled'] ?? false) ? 1 : 0,
        'automation_deadline_warning_hours' => armsAutomacaoInt($entrada['deadline_warning_hours'] ?? 72, 72, 1, 168),
        'automation_deadline_overdue_enabled' => armsAutomacaoBool($entrada['deadline_overdue_enabled'] ?? false) ? 1 : 0,
        'automation_retention_cleanup_enabled' => armsAutomacaoBool($entrada['retention_cleanup_enabled'] ?? false) ? 1 : 0,
    ];

    $descricoes = [
        'automation_invite_resend_enabled' => 'Ativa o reenvio automatico de convites pendentes.',
        'automation_invite_resend_days' => 'Dias de espera antes de reenviar convite pendente.',
        'automation_deadline_warning_enabled' => 'Ativa alertas automaticos antes do prazo do pedido terminar.',
        'automation_deadline_warning_hours' => 'Horas antes do deadline para criar alerta automatico.',
        'automation_deadline_overdue_enabled' => 'Ativa notificacoes automaticas para pedidos vencidos.',
        'automation_retention_cleanup_enabled' => 'Permite que rotinas agendadas executem limpeza de dados expirados.',
    ];

    foreach ($cfg as $chave => $valor) {
        armsConfiguracaoAtualizar($pdo, $chave, (string)$valor, $descricoes[$chave], $executedBy);
    }

    return $cfg;
}

function armsAutomacaoNotificarDeadlines(PDO $pdo, $tipo, $horas = 72, $executedBy = null) {
    armsPedidosGarantirDestinoInterno($pdo);

    $tipoAutomacao = $tipo === 'warning' ? 'deadline_warning' : 'deadline_overdue';
    $mensagem = $tipo === 'warning' ? 'Prazo de pedido a terminar' : 'Pedido urgente para responder';
    $where = $tipo === 'warning'
        ? "r.status IN ('SENT', 'RECEIVED') AND r.deadline_at >= NOW() AND r.deadline_at <= NOW() + ((:horas)::int * INTERVAL '1 hour')"
        : "r.status IN ('SENT', 'RECEIVED') AND r.deadline_at < NOW()";

    $sql = "
        WITH pedidos_alvo AS (
            SELECT
                r.id,
                r.reference,
                r.deadline_at,
                r.client_id,
                r.area_id,
                COALESCE(r.destination_type, 'CLIENT') AS destination_type,
                r.recipient_user_id
            FROM arms.request r
            WHERE $where
        ),
        destinatarios AS (
            SELECT p.id AS request_id, p.reference, p.deadline_at, cc.user_id AS recipient_id
            FROM pedidos_alvo p
            INNER JOIN arms.client_contact cc ON cc.client_id = p.client_id
            INNER JOIN arms.auth_user au ON au.id = cc.user_id
            WHERE cc.user_id IS NOT NULL
              AND au.is_active = TRUE
              AND p.destination_type = 'CLIENT'
            UNION
            SELECT p.id AS request_id, p.reference, p.deadline_at, p.recipient_user_id AS recipient_id
            FROM pedidos_alvo p
            INNER JOIN arms.auth_user au ON au.id = p.recipient_user_id
            WHERE p.destination_type = 'AKSANTI'
              AND p.recipient_user_id IS NOT NULL
              AND au.is_active = TRUE
            UNION
            SELECT p.id AS request_id, p.reference, p.deadline_at, au.id AS recipient_id
            FROM pedidos_alvo p
            INNER JOIN arms.auth_user au ON au.user_type = 'AKSANTI' AND au.is_active = TRUE
            WHERE p.recipient_user_id IS NULL
              AND (au.is_admin = TRUE
               OR EXISTS (
                   SELECT 1
                   FROM arms.area_membership am
                   WHERE am.user_id = au.id
                     AND am.area_id = p.area_id
               ))
        )
        INSERT INTO arms.notification (recipient_id, request_id, type, channel, payload)
        SELECT DISTINCT
            d.recipient_id,
            d.request_id,
            'DEADLINE',
            'IN_APP',
            jsonb_build_object(
                'pedido_ref', d.reference,
                'message', :mensagem::text,
                'deadline', to_char(d.deadline_at, 'DD/MM/YYYY HH24:MI'),
                'automacao_tipo', :tipo_automacao::text,
                'executed_by', :executed_by::uuid
            )
        FROM destinatarios d
        WHERE d.recipient_id IS NOT NULL
          AND NOT EXISTS (
              SELECT 1
              FROM arms.notification n
              WHERE n.recipient_id = d.recipient_id
                AND n.request_id = d.request_id
                AND n.type = 'DEADLINE'
                AND n.channel = 'IN_APP'
                AND COALESCE(n.payload->>'automacao_tipo', '') = :tipo_automacao
          )
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':mensagem', $mensagem, PDO::PARAM_STR);
    $stmt->bindValue(':tipo_automacao', $tipoAutomacao, PDO::PARAM_STR);
    $stmt->bindValue(':executed_by', $executedBy ?: '', PDO::PARAM_STR);
    if ($tipo === 'warning') {
        $stmt->bindValue(':horas', (int)$horas, PDO::PARAM_INT);
    }
    $stmt->execute();
    $notificacoesGeradas = $stmt->rowCount();

    if ($tipo === 'overdue') {
        $stmtUpdate = $pdo->prepare("
            UPDATE arms.request
            SET is_urgent = TRUE
            WHERE status IN ('SENT', 'RECEIVED')
              AND deadline_at < NOW()
              AND is_urgent = FALSE
        ");
        $stmtUpdate->execute();
    }

    return $notificacoesGeradas;
}

function armsAutomacaoUtilizadorElegivel(PDO $pdo, $id, $dias) {
    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.email,
            u.user_type,
            COALESCE(NULLIF(p.full_name, ''), u.email) AS full_name,
            cliente.id AS client_id,
            cliente.name,
            cliente.tax_id,
            cliente.location
        FROM arms.auth_user u
        LEFT JOIN arms.user_profile p ON p.user_id = u.id
        LEFT JOIN LATERAL (
            SELECT c.id, c.name, c.tax_id, c.location
            FROM arms.client_contact cc
            JOIN arms.client c ON c.id = cc.client_id
            WHERE cc.user_id = u.id
            ORDER BY cc.created_at DESC
            LIMIT 1
        ) cliente ON TRUE
        WHERE u.id = :id
          AND u.is_active = TRUE
          AND u.last_login_at IS NULL
          AND u.created_at <= NOW() - ((:dias)::int * INTERVAL '1 day')
          AND NOT EXISTS (
              SELECT 1
              FROM arms.notification n
              WHERE n.recipient_id = u.id
                AND n.type = 'INVITE_RESENT'
                AND n.channel = 'EMAIL'
                AND n.created_at >= NOW() - ((:dias)::int * INTERVAL '1 day')
          )
        FOR UPDATE OF u
    ");
    $stmt->execute([':id' => $id, ':dias' => $dias]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function armsAutomacaoReenviarConvites(PDO $pdo, $dias, $executedBy = null) {
    armsSenhaPoliticaGarantirEstrutura($pdo);

    $stmtIds = $pdo->prepare("
        SELECT u.id
        FROM arms.auth_user u
        WHERE u.is_active = TRUE
          AND u.last_login_at IS NULL
          AND u.created_at <= NOW() - ((:dias)::int * INTERVAL '1 day')
          AND NOT EXISTS (
              SELECT 1
              FROM arms.notification n
              WHERE n.recipient_id = u.id
                AND n.type = 'INVITE_RESENT'
                AND n.channel = 'EMAIL'
                AND n.created_at >= NOW() - ((:dias)::int * INTERVAL '1 day')
          )
        ORDER BY u.created_at ASC
        LIMIT 20
    ");
    $stmtIds->execute([':dias' => $dias]);
    $ids = $stmtIds->fetchAll(PDO::FETCH_COLUMN);
    $resultado = ['enviados' => 0, 'falhados' => 0, 'ignorados' => 0, 'erros' => []];

    foreach ($ids as $id) {
        try {
            $pdo->beginTransaction();
            $utilizador = armsAutomacaoUtilizadorElegivel($pdo, $id, $dias);

            if (!$utilizador) {
                $pdo->rollBack();
                $resultado['ignorados']++;
                continue;
            }

            if (!filter_var($utilizador['email'], FILTER_VALIDATE_EMAIL)) {
                $pdo->rollBack();
                $resultado['falhados']++;
                $resultado['erros'][] = 'E-mail invalido: ' . $utilizador['email'];
                continue;
            }

            $clienteAssociado = null;
            if ($utilizador['user_type'] === 'CLIENT') {
                if (empty($utilizador['client_id'])) {
                    $pdo->rollBack();
                    $resultado['falhados']++;
                    $resultado['erros'][] = 'Utilizador cliente sem empresa: ' . $utilizador['email'];
                    continue;
                }

                $clienteAssociado = [
                    'name' => $utilizador['name'],
                    'tax_id' => $utilizador['tax_id'],
                    'location' => $utilizador['location'],
                ];
            }

            $novaSenha = armsGerarSenhaInicial();
            $hash = password_hash($novaSenha, PASSWORD_BCRYPT, ['cost' => 12]);

            $stmtUpdate = $pdo->prepare("
                UPDATE arms.auth_user
                SET password_hash = :password_hash,
                    password_changed_at = NOW()
                WHERE id = :id
            ");
            $stmtUpdate->execute([':password_hash' => $hash, ':id' => $utilizador['id']]);

            $convite = armsMontarConviteUtilizador($utilizador['full_name'], $utilizador['email'], $novaSenha, $utilizador['user_type'], $clienteAssociado);
            armsEnviarEmail($utilizador['email'], $convite['assunto'], $convite['titulo'], $convite['conteudo_html']);

            $stmtNotif = $pdo->prepare("
                INSERT INTO arms.notification (recipient_id, type, channel, payload)
                VALUES (:recipient_id, 'INVITE_RESENT', 'EMAIL', CAST(:payload AS jsonb))
            ");
            $stmtNotif->execute([
                ':recipient_id' => $utilizador['id'],
                ':payload' => json_encode(['message' => 'Convite reenviado automaticamente', 'email' => $utilizador['email'], 'executed_by' => $executedBy], JSON_UNESCAPED_UNICODE),
            ]);

            $pdo->commit();
            $resultado['enviados']++;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $resultado['falhados']++;
            $erroMsg = armsEmailErroAmigavel($e);
            $resultado['erros'][] = $erroMsg;
            error_log('[ARMS] Automacao de convite falhou: ' . $e->getMessage());

            try {
                $pdo->beginTransaction();
                // Notificar o proprio utilizador para o historico
                $stmtErro = $pdo->prepare("
                    INSERT INTO arms.notification (recipient_id, type, channel, payload)
                    VALUES (:recipient_id, 'SYSTEM_ERROR', 'EMAIL', CAST(:payload AS jsonb))
                ");
                $stmtErro->execute([
                    ':recipient_id' => $id,
                    ':payload' => json_encode(['message' => 'Falha ao enviar convite via SMTP.', 'erro_tecnico' => $erroMsg], JSON_UNESCAPED_UNICODE),
                ]);

                // Notificar Super Admins
                $stmtAdmins = $pdo->query("SELECT id FROM arms.auth_user WHERE is_admin = TRUE AND is_active = TRUE AND user_type = 'AKSANTI'");
                $admins = $stmtAdmins->fetchAll(PDO::FETCH_COLUMN);
                foreach ($admins as $adminId) {
                    $stmtErro->execute([
                        ':recipient_id' => $adminId,
                        ':payload' => json_encode(['message' => 'Alerta: Falha ao reenviar convite via automação.', 'email_destino' => $utilizador['email'] ?? 'Desconhecido', 'erro_tecnico' => $erroMsg], JSON_UNESCAPED_UNICODE),
                    ]);
                }
                $pdo->commit();
            } catch (Throwable $e2) {
                if ($pdo->inTransaction()) $pdo->rollBack();
            }
        }
    }

    return $resultado;
}

function armsAutomacaoExecutar(PDO $pdo, array $cfg, $executedBy = null) {
    $resultado = [
        'deadline_vencido_notificacoes' => 0,
        'deadline_alerta_notificacoes' => 0,
        'convites' => ['enviados' => 0, 'falhados' => 0, 'ignorados' => 0, 'erros' => []],
        'retencao' => $cfg['retention_cleanup_enabled'] ? 'Limpeza agendada ativa. Use a rotina agendada para executar com relatorio.' : 'Limpeza agendada desativada.',
    ];

    if ($cfg['deadline_overdue_enabled']) {
        $resultado['deadline_vencido_notificacoes'] = armsAutomacaoNotificarDeadlines($pdo, 'overdue', 0, $executedBy);
    }
    if ($cfg['deadline_warning_enabled']) {
        $resultado['deadline_alerta_notificacoes'] = armsAutomacaoNotificarDeadlines($pdo, 'warning', $cfg['deadline_warning_hours'], $executedBy);
    }
    if ($cfg['invite_resend_enabled']) {
        $resultado['convites'] = armsAutomacaoReenviarConvites($pdo, $cfg['invite_resend_days'], $executedBy);
    }

    $resumoTexto = sprintf('Convites enviados: %d. Alertas de prazo: %d. Urgentes: %d.', (int)$resultado['convites']['enviados'], (int)$resultado['deadline_alerta_notificacoes'], (int)$resultado['deadline_vencido_notificacoes']);
    armsConfiguracaoAtualizar($pdo, 'automation_last_run_at', date('Y-m-d H:i:s'), 'Ultima execucao manual das automacoes.', $executedBy);
    armsConfiguracaoAtualizar($pdo, 'automation_last_run_summary', $resumoTexto, 'Resumo da ultima execucao manual das automacoes.', $executedBy);

    return $resultado;
}

try {
    armsExigirPermissao($pdo, 'automacao.gerir', 'Não tem permissão para gerir automação.');
    $acao = $_GET['acao'] ?? 'resumo';
    $executedBy = $_SESSION['arms_user_id'] ?? null;

    if ($acao === 'salvar') {
        $entrada = json_decode(file_get_contents('php://input'), true) ?: [];
        armsAutomacaoSalvar($pdo, $entrada, $executedBy);
        echo json_encode(['sucesso' => true, 'mensagem' => 'Regras de automacao guardadas com sucesso.', 'dados' => armsAutomacaoResumo($pdo)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($acao === 'executar') {
        $cfg = armsAutomacaoConfiguracoes($pdo);
        $resultado = armsAutomacaoExecutar($pdo, $cfg, $executedBy);
        echo json_encode(['sucesso' => true, 'mensagem' => 'Automacoes executadas com sucesso.', 'resultado' => $resultado, 'dados' => armsAutomacaoResumo($pdo)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['sucesso' => true, 'dados' => armsAutomacaoResumo($pdo)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('[ARMS] Erro na automacao admin: ' . $e->getMessage());
    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro na automacao administrativa: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
