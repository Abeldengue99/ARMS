<?php

require_once __DIR__ . '/configuracoes-servico.php';

function armsRetencaoPoliticasPadrao() {
    return [
        ['comment_draft_cache', 'Rascunhos locais de comentários ainda não enviados.', 2, 'Browser localStorage'],
        ['comments', 'Comentários oficiais enviados nos pedidos.', 2555, 'PostgreSQL: arms.request_comment'],
        ['comment_revisions', 'Versões antigas dos comentários editados.', 2555, 'PostgreSQL: arms.request_comment_revision'],
        ['attachments', 'Anexos atuais dos pedidos.', 2555, 'PostgreSQL: arms.attachment + pasta uploads/'],
        ['attachment_versions', 'Versões antigas de anexos atualizados.', 2555, 'PostgreSQL: arms.attachment_version + pasta uploads/'],
        ['request_responses', 'Respostas formais registadas nos pedidos.', 2555, 'PostgreSQL: arms.request_response'],
        ['request_audit_log', 'Timeline e histórico de alteração de estado dos pedidos.', 3650, 'PostgreSQL: arms.request_audit_log'],
        ['notifications_read', 'Notificações internas já lidas.', 180, 'PostgreSQL: arms.notification'],
        ['notifications_unread', 'Notificações internas ainda não lidas.', 365, 'PostgreSQL: arms.notification'],
        ['daily_backups', 'Backups diários cifrados.', 35, 'Storage de backups'],
        ['monthly_backups', 'Backups mensais cifrados.', 365, 'Storage de backups'],
        ['annual_backups', 'Backups anuais cifrados.', 2555, 'Storage de backups'],
    ];
}

function armsRetencaoAgora() {
    $timezone = getenv('ARMS_TIMEZONE') ?: 'Africa/Luanda';
    return new DateTimeImmutable('now', new DateTimeZone($timezone));
}

function armsRetencaoGarantirEstrutura(PDO $pdo) {
    armsConfiguracoesGarantirEstrutura($pdo);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS arms.request_comment_revision (
            id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            comment_id UUID NOT NULL REFERENCES arms.request_comment(id) ON DELETE CASCADE,
            body TEXT NOT NULL,
            revision_number INTEGER NOT NULL,
            edited_by UUID REFERENCES arms.auth_user(id),
            edited_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )
    ");

    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_comment_revision_comment
        ON arms.request_comment_revision (comment_id, edited_at)
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS arms.attachment_version (
            id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            attachment_id UUID NOT NULL REFERENCES arms.attachment(id) ON DELETE CASCADE,
            file_name VARCHAR(255) NOT NULL,
            content_type VARCHAR(120),
            size_bytes BIGINT,
            storage_key TEXT NOT NULL,
            replaced_by UUID REFERENCES arms.auth_user(id),
            replaced_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )
    ");

    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_attachment_version_attachment
        ON arms.attachment_version (attachment_id, replaced_at)
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS arms.data_retention_policy (
            policy_key VARCHAR(80) PRIMARY KEY,
            description TEXT NOT NULL,
            retention_days INTEGER NOT NULL,
            storage_place TEXT NOT NULL,
            updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS arms.data_retention_run (
            id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            action VARCHAR(32) NOT NULL,
            dry_run BOOLEAN NOT NULL DEFAULT TRUE,
            report_path TEXT,
            payload JSONB,
            executed_by UUID REFERENCES arms.auth_user(id),
            created_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )
    ");

    $stmt = $pdo->prepare("
        INSERT INTO arms.data_retention_policy (policy_key, description, retention_days, storage_place)
        VALUES (:policy_key, :description, :retention_days, :storage_place)
        ON CONFLICT (policy_key) DO UPDATE
        SET description = EXCLUDED.description,
            storage_place = EXCLUDED.storage_place,
            updated_at = now()
    ");

    foreach (armsRetencaoPoliticasPadrao() as $politica) {
        $stmt->execute([
            ':policy_key' => $politica[0],
            ':description' => $politica[1],
            ':retention_days' => $politica[2],
            ':storage_place' => $politica[3],
        ]);
    }
}

function armsRetencaoAtualizarPoliticas(PDO $pdo, array $politicas, $executedBy = null) {
    armsRetencaoGarantirEstrutura($pdo);

    $atuais = armsRetencaoPoliticas($pdo);
    $chavesPermitidas = array_column($atuais, 'policy_key');
    $alteracoes = [];

    $stmt = $pdo->prepare("
        UPDATE arms.data_retention_policy
        SET retention_days = :retention_days,
            updated_at = now()
        WHERE policy_key = :policy_key
    ");

    foreach ($politicas as $politica) {
        $chave = trim((string)($politica['policy_key'] ?? ''));
        $dias = filter_var($politica['retention_days'] ?? null, FILTER_VALIDATE_INT);

        if (!$chave || !in_array($chave, $chavesPermitidas, true)) {
            continue;
        }

        if ($dias === false || $dias < 1 || $dias > 36500) {
            throw new InvalidArgumentException('Informe tempos de retenção entre 1 e 36500 dias.');
        }

        $stmt->execute([
            ':policy_key' => $chave,
            ':retention_days' => (int)$dias,
        ]);

        $alteracoes[$chave] = (int)$dias;
    }

    if ($alteracoes) {
        armsRetencaoRegistarExecucao($pdo, 'POLICY_UPDATE', true, null, [
            'politicas' => $alteracoes,
        ], $executedBy);
    }

    return $alteracoes;
}

function armsRetencaoAtualizarConfiguracoes(PDO $pdo, array $entrada, $executedBy = null) {
    armsRetencaoGarantirEstrutura($pdo);

    $alteracoes = [];

    if (array_key_exists('attachment_max_size_mb', $entrada)) {
        $maxMb = filter_var($entrada['attachment_max_size_mb'], FILTER_VALIDATE_INT);

        if ($maxMb === false || $maxMb < 1 || $maxMb > 10240) {
            throw new InvalidArgumentException('O tamanho máximo de documentos deve estar entre 1MB e 10240MB.');
        }

        armsConfiguracaoAtualizar(
            $pdo,
            'attachment_max_size_mb',
            (string)$maxMb,
            'Tamanho máximo permitido para documentos/anexos em MB.',
            $executedBy
        );
        $alteracoes['attachment_max_size_mb'] = (int)$maxMb;
    }

    if (!empty($entrada['politicas']) && is_array($entrada['politicas'])) {
        $alteracoes['politicas'] = armsRetencaoAtualizarPoliticas($pdo, $entrada['politicas'], $executedBy);
    }

    if ($alteracoes) {
        armsRetencaoRegistarExecucao($pdo, 'SETTINGS_UPDATE', true, null, $alteracoes, $executedBy);
    }

    return $alteracoes;
}

function armsRetencaoPoliticas(PDO $pdo) {
    $stmt = $pdo->query("
        SELECT policy_key,
               description,
               retention_days,
               storage_place,
               to_char(updated_at, 'YYYY-MM-DD HH24:MI') as updated_at
        FROM arms.data_retention_policy
        ORDER BY
            CASE policy_key
                WHEN 'comment_draft_cache' THEN 1
                WHEN 'comments' THEN 2
                WHEN 'comment_revisions' THEN 3
                WHEN 'attachments' THEN 4
                WHEN 'attachment_versions' THEN 5
                WHEN 'request_responses' THEN 6
                WHEN 'request_audit_log' THEN 7
                WHEN 'notifications_read' THEN 8
                WHEN 'notifications_unread' THEN 9
                WHEN 'daily_backups' THEN 10
                WHEN 'monthly_backups' THEN 11
                WHEN 'annual_backups' THEN 12
                ELSE 99
            END,
            policy_key
    ");

    return $stmt->fetchAll();
}

function armsRetencaoDias(array $politicas, $chave, $padrao) {
    foreach ($politicas as $politica) {
        if (($politica['policy_key'] ?? '') === $chave) {
            return max(0, (int)$politica['retention_days']);
        }
    }

    return $padrao;
}

function armsRetencaoContar(PDO $pdo, $sql, array $params = []) {
    $stmt = $pdo->prepare($sql);

    foreach ($params as $chave => $valor) {
        $stmt->bindValue($chave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function armsRetencaoCandidatos(PDO $pdo, array $politicas) {
    $diasComentarios = armsRetencaoDias($politicas, 'comments', 2555);
    $diasRevisoesComentarios = armsRetencaoDias($politicas, 'comment_revisions', 2555);
    $diasAnexos = armsRetencaoDias($politicas, 'attachments', 2555);
    $diasVersoesAnexos = armsRetencaoDias($politicas, 'attachment_versions', 2555);
    $diasRespostas = armsRetencaoDias($politicas, 'request_responses', 2555);
    $diasAuditoria = armsRetencaoDias($politicas, 'request_audit_log', 3650);
    $diasNotificacoesLidas = armsRetencaoDias($politicas, 'notifications_read', 180);
    $diasNotificacoesNaoLidas = armsRetencaoDias($politicas, 'notifications_unread', 365);

    $candidatos = [];

    $candidatos[] = [
        'chave' => 'notifications_read',
        'nome' => 'Notificações lidas expiradas',
        'quantidade' => armsRetencaoContar($pdo, "
            SELECT COUNT(*)
            FROM arms.notification
            WHERE is_read = TRUE
              AND created_at < NOW() - ((:dias)::int * INTERVAL '1 day')
        ", [':dias' => $diasNotificacoesLidas]),
        'retencao_dias' => $diasNotificacoesLidas,
        'limpeza_automatica' => true,
        'acao' => 'Pode ser apagado após relatório e autorização.',
    ];

    $candidatos[] = [
        'chave' => 'notifications_unread',
        'nome' => 'Notificações não lidas expiradas',
        'quantidade' => armsRetencaoContar($pdo, "
            SELECT COUNT(*)
            FROM arms.notification
            WHERE is_read = FALSE
              AND created_at < NOW() - ((:dias)::int * INTERVAL '1 day')
        ", [':dias' => $diasNotificacoesNaoLidas]),
        'retencao_dias' => $diasNotificacoesNaoLidas,
        'limpeza_automatica' => true,
        'acao' => 'Pode ser apagado após relatório e autorização.',
    ];

    $candidatos[] = [
        'chave' => 'comments',
        'nome' => 'Comentários oficiais em pedidos encerrados',
        'quantidade' => armsRetencaoContar($pdo, "
            SELECT COUNT(*)
            FROM arms.request_comment rc
            INNER JOIN arms.request r ON r.id = rc.request_id
            WHERE r.closed_at IS NOT NULL
              AND r.closed_at < NOW() - ((:dias)::int * INTERVAL '1 day')
        ", [':dias' => $diasComentarios]),
        'retencao_dias' => $diasComentarios,
        'limpeza_automatica' => false,
        'acao' => 'Revisão manual obrigatória antes de qualquer eliminação.',
    ];

    $candidatos[] = [
        'chave' => 'comment_revisions',
        'nome' => 'Versões antigas de comentários',
        'quantidade' => armsRetencaoContar($pdo, "
            SELECT COUNT(*)
            FROM arms.request_comment_revision rcr
            INNER JOIN arms.request_comment rc ON rc.id = rcr.comment_id
            INNER JOIN arms.request r ON r.id = rc.request_id
            WHERE r.closed_at IS NOT NULL
              AND r.closed_at < NOW() - ((:dias)::int * INTERVAL '1 day')
        ", [':dias' => $diasRevisoesComentarios]),
        'retencao_dias' => $diasRevisoesComentarios,
        'limpeza_automatica' => false,
        'acao' => 'Revisão manual obrigatória antes de qualquer eliminação.',
    ];

    $candidatos[] = [
        'chave' => 'attachments',
        'nome' => 'Anexos de pedidos encerrados',
        'quantidade' => armsRetencaoContar($pdo, "
            SELECT COUNT(*)
            FROM arms.attachment a
            INNER JOIN arms.request r ON r.id = a.request_id
            WHERE a.request_id IS NOT NULL
              AND r.closed_at IS NOT NULL
              AND r.closed_at < NOW() - ((:dias)::int * INTERVAL '1 day')
        ", [':dias' => $diasAnexos]),
        'retencao_dias' => $diasAnexos,
        'limpeza_automatica' => false,
        'acao' => 'Revisão manual obrigatória antes de remover metadados e ficheiros.',
    ];

    $candidatos[] = [
        'chave' => 'attachment_versions',
        'nome' => 'Versões antigas de anexos',
        'quantidade' => armsRetencaoContar($pdo, "
            SELECT COUNT(*)
            FROM arms.attachment_version av
            INNER JOIN arms.attachment a ON a.id = av.attachment_id
            INNER JOIN arms.request r ON r.id = a.request_id
            WHERE a.request_id IS NOT NULL
              AND r.closed_at IS NOT NULL
              AND r.closed_at < NOW() - ((:dias)::int * INTERVAL '1 day')
        ", [':dias' => $diasVersoesAnexos]),
        'retencao_dias' => $diasVersoesAnexos,
        'limpeza_automatica' => false,
        'acao' => 'Revisão manual obrigatória antes de remover metadados e ficheiros.',
    ];

    $candidatos[] = [
        'chave' => 'request_responses',
        'nome' => 'Respostas formais em pedidos encerrados',
        'quantidade' => armsRetencaoContar($pdo, "
            SELECT COUNT(*)
            FROM arms.request_response rr
            INNER JOIN arms.request r ON r.id = rr.request_id
            WHERE r.closed_at IS NOT NULL
              AND r.closed_at < NOW() - ((:dias)::int * INTERVAL '1 day')
        ", [':dias' => $diasRespostas]),
        'retencao_dias' => $diasRespostas,
        'limpeza_automatica' => false,
        'acao' => 'Revisão manual obrigatória antes de qualquer eliminação.',
    ];

    $candidatos[] = [
        'chave' => 'request_audit_log',
        'nome' => 'Eventos da timeline/auditoria',
        'quantidade' => armsRetencaoContar($pdo, "
            SELECT COUNT(*)
            FROM arms.request_audit_log ral
            INNER JOIN arms.request r ON r.id = ral.request_id
            WHERE r.closed_at IS NOT NULL
              AND r.closed_at < NOW() - ((:dias)::int * INTERVAL '1 day')
        ", [':dias' => $diasAuditoria]),
        'retencao_dias' => $diasAuditoria,
        'limpeza_automatica' => false,
        'acao' => 'Auditoria imutável: apenas sinalizar para revisão jurídica/auditoria.',
    ];

    return $candidatos;
}

function armsRetencaoResumoCandidatos(array $candidatos) {
    $automatico = 0;
    $manual = 0;

    foreach ($candidatos as $candidato) {
        if (!empty($candidato['limpeza_automatica'])) {
            $automatico += (int)$candidato['quantidade'];
        } else {
            $manual += (int)$candidato['quantidade'];
        }
    }

    return [
        'total_limpeza_automatica' => $automatico,
        'total_revisao_manual' => $manual,
        'total_candidatos' => $automatico + $manual,
    ];
}

function armsRetencaoUltimaExecucao(PDO $pdo) {
    $stmt = $pdo->query("
        SELECT id::text,
               action,
               dry_run,
               report_path,
               to_char(created_at, 'YYYY-MM-DD HH24:MI') as created_at
        FROM arms.data_retention_run
        ORDER BY created_at DESC
        LIMIT 1
    ");

    $ultima = $stmt->fetch();
    return $ultima ?: null;
}

function armsRetencaoMontarRelatorio(PDO $pdo, $executedBy = null) {
    $politicas = armsRetencaoPoliticas($pdo);
    $candidatos = armsRetencaoCandidatos($pdo, $politicas);

    return [
        'gerado_em' => armsRetencaoAgora()->format(DateTimeInterface::ATOM),
        'gerado_por' => $executedBy,
        'politicas' => $politicas,
        'candidatos' => $candidatos,
        'resumo' => armsRetencaoResumoCandidatos($candidatos),
        'autorizacao' => 'A limpeza destrutiva só deve ocorrer após relatório, validação de bloqueio legal/auditoria e autorização do Super Admin.',
        'limpeza_automatica_permitida' => 'Nesta fase, apenas notificações expiradas são apagadas automaticamente. Pedidos, comentários, anexos, respostas e timeline ficam apenas sinalizados para revisão.',
    ];
}

function armsRetencaoCaminhoRelatorio($acao) {
    $raiz = dirname(__DIR__);
    $diretorios = [];
    $diretorioConfigurado = getenv('ARMS_RETENTION_REPORT_DIR');

    if ($diretorioConfigurado) {
        $diretorios[] = [
            'absoluto' => $diretorioConfigurado,
            'relativo' => $diretorioConfigurado,
        ];
    }

    $diretorios[] = [
        'absoluto' => $raiz . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'retencao',
        'relativo' => 'backups/retencao',
    ];
    $diretorios[] = [
        'absoluto' => $raiz . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'retencao-relatorios',
        'relativo' => 'tmp/retencao-relatorios',
    ];

    $diretorioEscolhido = null;

    foreach ($diretorios as $diretorio) {
        $absoluto = $diretorio['absoluto'];

        if (!is_dir($absoluto)) {
            @mkdir($absoluto, 0777, true);
        }

        if (is_dir($absoluto) && is_writable($absoluto)) {
            $diretorioEscolhido = $diretorio;
            break;
        }
    }

    if (!$diretorioEscolhido) {
        throw new RuntimeException('Não foi possível encontrar uma pasta gravável para relatórios de retenção.');
    }

    $slug = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($acao));
    $nome = 'retencao-' . armsRetencaoAgora()->format('Ymd-His') . '-' . trim($slug, '-') . '.json';

    return [
        'absoluto' => rtrim($diretorioEscolhido['absoluto'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $nome,
        'relativo' => rtrim($diretorioEscolhido['relativo'], '/\\') . '/' . $nome,
    ];
}

function armsRetencaoRegistarExecucao(PDO $pdo, $acao, $dryRun, $reportPath, array $payload, $executedBy = null) {
    $stmt = $pdo->prepare("
        INSERT INTO arms.data_retention_run (action, dry_run, report_path, payload, executed_by)
        VALUES (:action, :dry_run, :report_path, CAST(:payload AS jsonb), :executed_by)
    ");

    $stmt->bindValue(':action', $acao, PDO::PARAM_STR);
    $stmt->bindValue(':dry_run', (bool)$dryRun, PDO::PARAM_BOOL);
    $stmt->bindValue(':report_path', $reportPath, $reportPath ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':payload', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PDO::PARAM_STR);

    if ($executedBy) {
        $stmt->bindValue(':executed_by', $executedBy, PDO::PARAM_STR);
    } else {
        $stmt->bindValue(':executed_by', null, PDO::PARAM_NULL);
    }

    $stmt->execute();
}

function armsRetencaoGerarRelatorio(PDO $pdo, $executedBy = null, $acao = 'REPORT') {
    armsRetencaoGarantirEstrutura($pdo);

    $relatorio = armsRetencaoMontarRelatorio($pdo, $executedBy);
    $caminho = armsRetencaoCaminhoRelatorio($acao);
    $json = json_encode($relatorio, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (file_put_contents($caminho['absoluto'], $json) === false) {
        throw new RuntimeException('Não foi possível escrever o relatório de retenção.');
    }

    armsRetencaoRegistarExecucao($pdo, $acao, true, $caminho['relativo'], $relatorio, $executedBy);

    return [
        'arquivo' => $caminho['relativo'],
        'relatorio' => $relatorio,
    ];
}

function armsRetencaoApagarNotificacoes(PDO $pdo, $lidas, $dias) {
    $stmt = $pdo->prepare("
        DELETE FROM arms.notification
        WHERE is_read = :lidas
          AND created_at < NOW() - ((:dias)::int * INTERVAL '1 day')
    ");
    $stmt->bindValue(':lidas', (bool)$lidas, PDO::PARAM_BOOL);
    $stmt->bindValue(':dias', (int)$dias, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->rowCount();
}

function armsRetencaoExecutarLimpeza(PDO $pdo, $executedBy = null) {
    $relatorio = armsRetencaoGerarRelatorio($pdo, $executedBy, 'PRE_CLEANUP_REPORT');
    $politicas = armsRetencaoPoliticas($pdo);

    $diasLidas = armsRetencaoDias($politicas, 'notifications_read', 180);
    $diasNaoLidas = armsRetencaoDias($politicas, 'notifications_unread', 365);

    $pdo->beginTransaction();

    try {
        $apagados = [
            'notifications_read' => armsRetencaoApagarNotificacoes($pdo, true, $diasLidas),
            'notifications_unread' => armsRetencaoApagarNotificacoes($pdo, false, $diasNaoLidas),
        ];

        $payload = [
            'apagados' => $apagados,
            'relatorio_previo' => $relatorio['arquivo'],
            'dados_oficiais' => 'Pedidos, comentários, anexos, respostas e timeline não foram apagados automaticamente; ficaram apenas sinalizados no relatório.',
        ];

        armsRetencaoRegistarExecucao($pdo, 'CLEANUP', false, $relatorio['arquivo'], $payload, $executedBy);
        $pdo->commit();

        return [
            'relatorio_previo' => $relatorio['arquivo'],
            'apagados' => $apagados,
            'mensagem' => 'Limpeza concluída. Apenas notificações expiradas foram removidas automaticamente.',
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function armsRetencaoResumo(PDO $pdo) {
    armsRetencaoGarantirEstrutura($pdo);

    $politicas = armsRetencaoPoliticas($pdo);
    $candidatos = armsRetencaoCandidatos($pdo, $politicas);

    return [
        'politicas' => $politicas,
        'candidatos' => $candidatos,
        'resumo' => armsRetencaoResumoCandidatos($candidatos),
        'ultima_execucao' => armsRetencaoUltimaExecucao($pdo),
        'configuracoes' => armsConfiguracoesResumo($pdo),
    ];
}
