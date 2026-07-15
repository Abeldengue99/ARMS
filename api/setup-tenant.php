<?php
require_once 'db.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Only accessible via CLI or Super Admin
    if (php_sapi_name() !== 'cli') {
        armsAuthExigirAdmin();
    }

    $pdo->beginTransaction();

    // 1. Create Tenant Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS arms.tenant (
            id BIGSERIAL PRIMARY KEY,
            name VARCHAR(190) NOT NULL,
            domain VARCHAR(190) NULL,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )
    ");

    // 2. Insert Default Tenant (Aksanti) if not exists
    $stmt = $pdo->query("SELECT id FROM arms.tenant WHERE id = 1");
    if (!$stmt->fetch()) {
        $pdo->exec("INSERT INTO arms.tenant (id, name, domain) VALUES (1, 'Aksanti', 'aksanti.local')");
        // Reset sequence so next ID is > 1
        $pdo->exec("SELECT setval('arms.tenant_id_seq', (SELECT MAX(id) FROM arms.tenant))");
    }

    // 3. Create Tenant Settings Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS arms.tenant_settings (
            tenant_id BIGINT PRIMARY KEY REFERENCES arms.tenant(id) ON DELETE CASCADE,
            system_name VARCHAR(190) NOT NULL DEFAULT 'ARMS',
            primary_color VARCHAR(32) NOT NULL DEFAULT '#d97706',
            logo_url VARCHAR(255) NULL,
            smtp_host VARCHAR(190) NULL,
            smtp_port INTEGER NULL,
            smtp_user VARCHAR(190) NULL,
            smtp_pass VARCHAR(255) NULL,
            smtp_from_email VARCHAR(190) NULL,
            smtp_from_name VARCHAR(190) NULL,
            updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )
    ");

    // 4. Insert Default Settings for Aksanti if not exists
    $stmt = $pdo->query("SELECT tenant_id FROM arms.tenant_settings WHERE tenant_id = 1");
    if (!$stmt->fetch()) {
        $pdo->exec("
            INSERT INTO arms.tenant_settings (tenant_id, system_name, primary_color) 
            VALUES (1, 'ARMS', '#d97706')
        ");
    }

    // 5. Add tenant_id to auth_user safely
    // Check if column exists
    $stmt = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_schema='arms' AND table_name='auth_user' AND column_name='tenant_id'
    ");
    
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE arms.auth_user ADD COLUMN tenant_id BIGINT NULL REFERENCES arms.tenant(id) ON DELETE CASCADE");
        $pdo->exec("UPDATE arms.auth_user SET tenant_id = 1 WHERE tenant_id IS NULL");
        $pdo->exec("ALTER TABLE arms.auth_user ALTER COLUMN tenant_id SET NOT NULL");
        $pdo->exec("ALTER TABLE arms.auth_user ALTER COLUMN tenant_id SET DEFAULT 1");
    }

    $pdo->commit();

    echo json_encode(['sucesso' => true, 'mensagem' => 'Estrutura SaaS criada com sucesso.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['sucesso' => false, 'erro' => 'Falha: ' . $e->getMessage()]);
}
