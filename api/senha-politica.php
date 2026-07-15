<?php

function armsSenhaPoliticaGarantirEstrutura(PDO $pdo): void {
    $pdo->exec("
        ALTER TABLE arms.auth_user
        ADD COLUMN IF NOT EXISTS password_changed_at TIMESTAMPTZ
    ");

    $pdo->exec("
        UPDATE arms.auth_user
        SET password_changed_at = COALESCE(last_login_at, created_at, NOW())
        WHERE password_changed_at IS NULL
    ");

    $pdo->exec("
        ALTER TABLE arms.auth_user
        ALTER COLUMN password_changed_at SET DEFAULT NOW()
    ");

    $pdo->exec("
        ALTER TABLE arms.auth_user
        ALTER COLUMN password_changed_at SET NOT NULL
    ");
}

function armsSenhaPoliticaDados($passwordChangedAt): array {
    $alteradaEm = $passwordChangedAt ? new DateTimeImmutable((string)$passwordChangedAt) : new DateTimeImmutable('@0');
    $expiraEm = $alteradaEm->modify('+6 months');
    $agora = new DateTimeImmutable('now');
    $expirada = $expiraEm <= $agora;
    $diasRestantes = $expirada ? 0 : (int)$agora->diff($expiraEm)->format('%a');

    return [
        'password_changed_at' => $alteradaEm->format('Y-m-d H:i:s'),
        'password_expires_at' => $expiraEm->format('Y-m-d H:i:s'),
        'password_expired' => $expirada,
        'password_days_remaining' => $diasRestantes,
    ];
}

function armsSenhaPoliticaAtualizar(PDO $pdo, string $userId): void {
    armsSenhaPoliticaGarantirEstrutura($pdo);
    $stmt = $pdo->prepare("UPDATE arms.auth_user SET password_changed_at = NOW() WHERE id = :id");
    $stmt->execute([':id' => $userId]);
}

?>
