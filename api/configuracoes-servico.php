<?php

function armsConfiguracoesPadrao() {
    return [
        'attachment_max_size_mb' => [
            'valor' => '50',
            'descricao' => 'Tamanho máximo permitido para documentos/anexos em MB.',
        ],
        'automation_invite_resend_enabled' => [
            'valor' => '0',
            'descricao' => 'Ativa o reenvio automatico de convites pendentes.',
        ],
        'automation_invite_resend_days' => [
            'valor' => '2',
            'descricao' => 'Dias de espera antes de reenviar convite pendente.',
        ],
        'automation_deadline_warning_enabled' => [
            'valor' => '1',
            'descricao' => 'Ativa alertas automaticos antes do prazo do pedido terminar.',
        ],
        'automation_deadline_warning_hours' => [
            'valor' => '24',
            'descricao' => 'Horas antes do deadline para criar alerta automatico.',
        ],
        'automation_deadline_overdue_enabled' => [
            'valor' => '1',
            'descricao' => 'Ativa notificacoes automaticas para pedidos vencidos.',
        ],
        'automation_retention_cleanup_enabled' => [
            'valor' => '0',
            'descricao' => 'Permite que rotinas agendadas executem limpeza de dados expirados.',
        ],
    ];
}

function armsConfiguracoesGarantirEstrutura(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS arms.system_setting (
            setting_key VARCHAR(120) PRIMARY KEY,
            setting_value TEXT NOT NULL,
            description TEXT,
            updated_by UUID REFERENCES arms.auth_user(id),
            updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )
    ");

    $stmt = $pdo->prepare("
        INSERT INTO arms.system_setting (setting_key, setting_value, description)
        VALUES (:setting_key, :setting_value, :description)
        ON CONFLICT (setting_key) DO NOTHING
    ");

    foreach (armsConfiguracoesPadrao() as $chave => $dados) {
        $stmt->execute([
            ':setting_key' => $chave,
            ':setting_value' => $dados['valor'],
            ':description' => $dados['descricao'],
        ]);
    }
}

function armsConfiguracaoObter(PDO $pdo, $chave, $padrao = null) {
    armsConfiguracoesGarantirEstrutura($pdo);

    $stmt = $pdo->prepare("
        SELECT setting_value
        FROM arms.system_setting
        WHERE setting_key = :setting_key
    ");
    $stmt->execute([':setting_key' => $chave]);
    $valor = $stmt->fetchColumn();

    return $valor !== false ? $valor : $padrao;
}

function armsConfiguracaoInteiro(PDO $pdo, $chave, $padrao, $minimo = 1, $maximo = 10240) {
    $valor = armsConfiguracaoObter($pdo, $chave, (string)$padrao);
    $numero = filter_var($valor, FILTER_VALIDATE_INT);

    if ($numero === false) {
        return $padrao;
    }

    return max($minimo, min($maximo, (int)$numero));
}

function armsConfiguracaoAtualizar(PDO $pdo, $chave, $valor, $descricao = null, $updatedBy = null) {
    armsConfiguracoesGarantirEstrutura($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO arms.system_setting (setting_key, setting_value, description, updated_by, updated_at)
        VALUES (:setting_key, :setting_value, :description, :updated_by, now())
        ON CONFLICT (setting_key) DO UPDATE
        SET setting_value = EXCLUDED.setting_value,
            description = COALESCE(EXCLUDED.description, system_setting.description),
            updated_by = EXCLUDED.updated_by,
            updated_at = now()
    ");

    $stmt->bindValue(':setting_key', $chave, PDO::PARAM_STR);
    $stmt->bindValue(':setting_value', (string)$valor, PDO::PARAM_STR);

    if ($descricao !== null) {
        $stmt->bindValue(':description', $descricao, PDO::PARAM_STR);
    } else {
        $stmt->bindValue(':description', null, PDO::PARAM_NULL);
    }

    if ($updatedBy) {
        $stmt->bindValue(':updated_by', $updatedBy, PDO::PARAM_STR);
    } else {
        $stmt->bindValue(':updated_by', null, PDO::PARAM_NULL);
    }

    $stmt->execute();
}

function armsConfiguracoesResumo(PDO $pdo) {
    return [
        'attachment_max_size_mb' => armsConfiguracaoInteiro($pdo, 'attachment_max_size_mb', 50, 1, 10240),
        'automation_invite_resend_enabled' => armsConfiguracaoInteiro($pdo, 'automation_invite_resend_enabled', 0, 0, 1),
        'automation_invite_resend_days' => armsConfiguracaoInteiro($pdo, 'automation_invite_resend_days', 2, 1, 90),
        'automation_deadline_warning_enabled' => armsConfiguracaoInteiro($pdo, 'automation_deadline_warning_enabled', 1, 0, 1),
        'automation_deadline_warning_hours' => armsConfiguracaoInteiro($pdo, 'automation_deadline_warning_hours', 24, 1, 168),
        'automation_deadline_overdue_enabled' => armsConfiguracaoInteiro($pdo, 'automation_deadline_overdue_enabled', 1, 0, 1),
        'automation_retention_cleanup_enabled' => armsConfiguracaoInteiro($pdo, 'automation_retention_cleanup_enabled', 0, 0, 1),
    ];
}
