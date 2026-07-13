-- ARMS - Retencao e auditoria complementar
-- Data: 2026-07-10
-- Objetivo: formalizar onde ficam guardadas revisoes de comentarios,
-- versoes antigas de anexos e a politica base de retencao.

SET search_path TO arms, public;

CREATE TABLE IF NOT EXISTS request_comment_revision (
    id              UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    comment_id      UUID        NOT NULL REFERENCES request_comment(id) ON DELETE CASCADE,
    body            TEXT        NOT NULL,
    revision_number INTEGER     NOT NULL,
    edited_by       UUID        REFERENCES auth_user(id),
    edited_at       TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_comment_revision_comment
    ON request_comment_revision (comment_id, edited_at);

COMMENT ON TABLE request_comment_revision
    IS 'Versoes antigas dos comentarios antes de cada edicao, para auditoria.';

CREATE TABLE IF NOT EXISTS attachment_version (
    id            UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    attachment_id UUID         NOT NULL REFERENCES attachment(id) ON DELETE CASCADE,
    file_name     VARCHAR(255) NOT NULL,
    content_type  VARCHAR(120),
    size_bytes    BIGINT,
    storage_key   TEXT         NOT NULL,
    replaced_by   UUID         REFERENCES auth_user(id),
    replaced_at   TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_attachment_version_attachment
    ON attachment_version (attachment_id, replaced_at);

COMMENT ON TABLE attachment_version
    IS 'Metadados das versoes antigas de anexos substituidos. O ficheiro antigo continua em uploads/storage_key.';

CREATE TABLE IF NOT EXISTS data_retention_policy (
    policy_key     VARCHAR(80) PRIMARY KEY,
    description    TEXT        NOT NULL,
    retention_days INTEGER     NOT NULL,
    storage_place  TEXT        NOT NULL,
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO data_retention_policy (policy_key, description, retention_days, storage_place)
VALUES
    ('comment_draft_cache', 'Rascunhos locais de comentarios ainda nao enviados.', 2, 'Browser localStorage'),
    ('comments', 'Comentarios oficiais enviados nos pedidos.', 2555, 'PostgreSQL: arms.request_comment'),
    ('comment_revisions', 'Versoes antigas dos comentarios editados.', 2555, 'PostgreSQL: arms.request_comment_revision'),
    ('attachments', 'Anexos atuais dos pedidos.', 2555, 'PostgreSQL: arms.attachment + pasta uploads/'),
    ('attachment_versions', 'Versoes antigas de anexos atualizados.', 2555, 'PostgreSQL: arms.attachment_version + pasta uploads/'),
    ('request_responses', 'Respostas formais registadas nos pedidos.', 2555, 'PostgreSQL: arms.request_response'),
    ('request_audit_log', 'Timeline e historico de alteracao de estado dos pedidos.', 3650, 'PostgreSQL: arms.request_audit_log'),
    ('notifications_read', 'Notificacoes internas ja lidas.', 180, 'PostgreSQL: arms.notification'),
    ('notifications_unread', 'Notificacoes internas ainda nao lidas.', 365, 'PostgreSQL: arms.notification'),
    ('daily_backups', 'Backups diarios cifrados.', 35, 'Storage de backups'),
    ('monthly_backups', 'Backups mensais cifrados.', 365, 'Storage de backups'),
    ('annual_backups', 'Backups anuais cifrados.', 2555, 'Storage de backups')
ON CONFLICT (policy_key) DO UPDATE
SET description = EXCLUDED.description,
    retention_days = EXCLUDED.retention_days,
    storage_place = EXCLUDED.storage_place,
    updated_at = now();

CREATE TABLE IF NOT EXISTS data_retention_run (
    id          UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    action      VARCHAR(32) NOT NULL,
    dry_run     BOOLEAN     NOT NULL DEFAULT TRUE,
    report_path TEXT,
    payload     JSONB,
    executed_by UUID        REFERENCES auth_user(id),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE data_retention_run
    IS 'Historico de relatorios e limpezas de retencao executadas pelo sistema.';

CREATE TABLE IF NOT EXISTS system_setting (
    setting_key   VARCHAR(120) PRIMARY KEY,
    setting_value TEXT         NOT NULL,
    description   TEXT,
    updated_by    UUID         REFERENCES auth_user(id),
    updated_at    TIMESTAMPTZ  NOT NULL DEFAULT now()
);

INSERT INTO system_setting (setting_key, setting_value, description)
VALUES
    ('attachment_max_size_mb', '50', 'Tamanho maximo permitido para documentos/anexos em MB.')
ON CONFLICT (setting_key) DO NOTHING;
