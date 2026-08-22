-- ============================================================
--  ARMS — Aksanti Request Management System
--  Complete PostgreSQL schema: tables, triggers, seed
--  Target: PostgreSQL 14+
--
--  Run order within this file:
--    1. Extensions
--    2. Schema
--    3. Identity & access
--    4. Organization (areas & membership)
--    5. Clients
--    6. Request numbering (sequence + function)
--    7. Requests (pedidos)
--    8. Attachments
--    9. Audit log
--   10. Notifications
--   11. Triggers (audit immutability, status auto-log, transition guard)
--   12. Seed data
-- ============================================================

-- ---------- 1. Extensions ----------
CREATE EXTENSION IF NOT EXISTS "pgcrypto";   -- gen_random_uuid()
CREATE EXTENSION IF NOT EXISTS "citext";     -- case-insensitive email

-- ---------- 2. Schema ----------
CREATE SCHEMA IF NOT EXISTS arms;
SET search_path TO arms, public;


-- ============================================================
--  3. IDENTITY & ACCESS
-- ============================================================

CREATE TABLE auth_user (
    id              UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    email           CITEXT       NOT NULL UNIQUE,
    password_hash   TEXT         NOT NULL,
    password_changed_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    user_type       VARCHAR(16)  NOT NULL,
    is_active       BOOLEAN      NOT NULL DEFAULT TRUE,
    is_admin        BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT now(),
    last_login_at   TIMESTAMPTZ,
    CONSTRAINT ck_user_type CHECK (user_type IN ('AKSANTI','CLIENT'))
);

COMMENT ON TABLE  auth_user            IS 'Login credentials and account type.';
COMMENT ON COLUMN auth_user.user_type  IS 'AKSANTI = internal staff, CLIENT = external contact.';


CREATE TABLE user_profile (
    user_id     UUID         PRIMARY KEY
                             REFERENCES auth_user(id) ON DELETE CASCADE,
    full_name   VARCHAR(160) NOT NULL,
    phone       VARCHAR(160),
    locale      VARCHAR(8)   NOT NULL DEFAULT 'pt-PT',
    avatar_url  TEXT
);

COMMENT ON TABLE user_profile IS 'Personal data, kept separate from credentials.';

CREATE TABLE user_permission (
    user_id        UUID        NOT NULL REFERENCES auth_user(id) ON DELETE CASCADE,
    permission_key VARCHAR(80) NOT NULL,
    granted_by     UUID        REFERENCES auth_user(id) ON DELETE SET NULL,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (user_id, permission_key)
);

CREATE TABLE user_permission_audit (
    id             BIGSERIAL   PRIMARY KEY,
    user_id        UUID        NOT NULL REFERENCES auth_user(id) ON DELETE CASCADE,
    permission_key VARCHAR(80) NOT NULL,
    action         VARCHAR(16) NOT NULL,
    changed_by     UUID        REFERENCES auth_user(id) ON DELETE SET NULL,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT ck_user_permission_audit_action CHECK (action IN ('GRANT','REVOKE'))
);

COMMENT ON TABLE user_permission IS 'Extra module permissions granted by Super Admins.';
COMMENT ON TABLE user_permission_audit IS 'Audit trail for manual permission changes.';

CREATE TABLE security_login_event (
    id          BIGSERIAL    PRIMARY KEY,
    user_id     UUID         REFERENCES auth_user(id) ON DELETE SET NULL,
    email       VARCHAR(190) NOT NULL,
    ip_address  VARCHAR(64)  NOT NULL,
    user_agent  TEXT,
    event_type  VARCHAR(32)  NOT NULL,
    success     BOOLEAN      NOT NULL DEFAULT FALSE,
    reason      TEXT,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE security_login_lock (
    id              BIGSERIAL    PRIMARY KEY,
    email           VARCHAR(190) NOT NULL,
    ip_address      VARCHAR(64)  NOT NULL,
    attempts        INTEGER      NOT NULL DEFAULT 0,
    blocked_until   TIMESTAMPTZ  NOT NULL,
    last_attempt_at TIMESTAMPTZ  NOT NULL DEFAULT now(),
    unlocked_by     UUID         REFERENCES auth_user(id) ON DELETE SET NULL,
    unlocked_at     TIMESTAMPTZ,
    UNIQUE (email, ip_address)
);

CREATE TABLE security_alert (
    id          BIGSERIAL    PRIMARY KEY,
    user_id     UUID         REFERENCES auth_user(id) ON DELETE SET NULL,
    email       VARCHAR(190) NOT NULL,
    ip_address  VARCHAR(64)  NOT NULL,
    severity    VARCHAR(16)  NOT NULL DEFAULT 'MEDIUM',
    message     TEXT         NOT NULL,
    status      VARCHAR(16)  NOT NULL DEFAULT 'OPEN',
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT now(),
    resolved_at TIMESTAMPTZ,
    resolved_by UUID         REFERENCES auth_user(id) ON DELETE SET NULL
);

CREATE TABLE security_active_session (
    session_hash VARCHAR(64) PRIMARY KEY,
    user_id      UUID        NOT NULL REFERENCES auth_user(id) ON DELETE CASCADE,
    ip_address   VARCHAR(64) NOT NULL,
    user_agent   TEXT,
    started_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    last_seen_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    ended_at     TIMESTAMPTZ
);

CREATE TABLE php_session (
    id            VARCHAR(128) PRIMARY KEY,
    data          TEXT         NOT NULL DEFAULT '',
    last_activity TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE INDEX idx_security_login_event_email_ip_created
    ON security_login_event (email, ip_address, created_at DESC);

CREATE INDEX idx_security_alert_status_created
    ON security_alert (status, created_at DESC);

CREATE INDEX idx_security_active_session_live
    ON security_active_session (ended_at, last_seen_at DESC);

CREATE INDEX idx_php_session_last_activity
    ON php_session (last_activity);

COMMENT ON TABLE security_login_event IS 'Login success, failure and block history for automated security.';
COMMENT ON TABLE security_login_lock IS 'Temporary login locks after repeated failures.';
COMMENT ON TABLE security_alert IS 'Security alerts visible in the admin area.';
COMMENT ON TABLE security_active_session IS 'Active browser sessions tracked by session hash.';
COMMENT ON TABLE php_session IS 'PHP session storage shared by all web containers.';


-- ============================================================
--  4. ORGANIZATION (AREAS & MEMBERSHIP)
-- ============================================================

CREATE TABLE area (
    id              UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    code            VARCHAR(24)  NOT NULL UNIQUE,   -- 'RH','CONTAB','TECH'
    name            VARCHAR(80)  NOT NULL,
    is_restricted   BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT now()
);

COMMENT ON COLUMN area.is_restricted
    IS 'TRUE for sensitive areas (e.g. RH) — drives visibility filtering.';


CREATE TABLE area_membership (
    user_id    UUID         NOT NULL REFERENCES auth_user(id) ON DELETE CASCADE,
    area_id    UUID         NOT NULL REFERENCES area(id)       ON DELETE CASCADE,
    role       VARCHAR(16)  NOT NULL,
    created_at TIMESTAMPTZ  NOT NULL DEFAULT now(),
    PRIMARY KEY (user_id, area_id),
    CONSTRAINT ck_membership_role CHECK (role IN ('MEMBER','MANAGER'))
);

COMMENT ON TABLE area_membership
    IS 'Which Aksanti users may see which areas (M:N).';


-- ============================================================
--  5. CLIENTS
-- ============================================================

CREATE TABLE client (
    id              UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    name            VARCHAR(160) NOT NULL,
    primary_email   CITEXT       NOT NULL,
    is_active       BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT now()
);

COMMENT ON TABLE client IS 'The client organization (not the individual person).';


CREATE TABLE client_contact (
    id          UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    client_id   UUID         NOT NULL REFERENCES client(id) ON DELETE CASCADE,
    user_id     UUID         REFERENCES auth_user(id) ON DELETE SET NULL,
    email       CITEXT       NOT NULL,
    full_name   VARCHAR(160),
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT now(),
    CONSTRAINT uq_client_contact_email UNIQUE (client_id, email)
);

COMMENT ON TABLE client_contact
    IS 'People at a client; optionally linked to a login user.';


-- ============================================================
--  6. REQUEST NUMBERING (year-scoped human reference)
-- ============================================================

CREATE TABLE request_sequence (
    year        INT     PRIMARY KEY,
    last_value  BIGINT  NOT NULL DEFAULT 0
);

COMMENT ON TABLE request_sequence
    IS 'Generates year-scoped references like AKS-2026-00042, transactionally.';

CREATE OR REPLACE FUNCTION next_request_reference()
RETURNS TEXT AS $$
DECLARE
    y   INT    := EXTRACT(YEAR FROM now())::INT;
    seq BIGINT;
BEGIN
    INSERT INTO request_sequence (year, last_value)
        VALUES (y, 1)
    ON CONFLICT (year) DO UPDATE
        SET last_value = request_sequence.last_value + 1
    RETURNING last_value INTO seq;

    RETURN 'AKS-' || y::TEXT || '-' || lpad(seq::TEXT, 5, '0');
END;
$$ LANGUAGE plpgsql;


-- ============================================================
--  7. REQUESTS (PEDIDOS)
-- ============================================================

CREATE TABLE request (
    id              UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    reference       TEXT         NOT NULL UNIQUE
                                 DEFAULT next_request_reference(),  -- Nº do Pedido
    title           VARCHAR(200) NOT NULL,             -- Título do pedido
    description     TEXT         NOT NULL,             -- Descrição do pedido
    area_id         UUID         NOT NULL REFERENCES area(id),       -- Área do pedido
    client_id       UUID         NOT NULL REFERENCES client(id),     -- Nome do Cliente
    client_email    CITEXT       NOT NULL,             -- Email do Cliente (snapshot)
    destination_type VARCHAR(16) NOT NULL DEFAULT 'CLIENT',
    recipient_user_id UUID,
    created_by      UUID         NOT NULL REFERENCES auth_user(id),  -- Quem criou
    status          VARCHAR(20)  NOT NULL DEFAULT 'DRAFT',
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT now(),  -- Data de Criação
    deadline_at     TIMESTAMPTZ  NOT NULL,                -- Data de Deadline
    sent_at         TIMESTAMPTZ,
    closed_at       TIMESTAMPTZ,
    CONSTRAINT ck_request_status CHECK (status IN (
        'DRAFT','SENT','RECEIVED','CLIENT_RESPONDED',
        'ACCEPTED','REJECTED','CLOSED'
    )),
    CONSTRAINT ck_deadline_after_created CHECK (deadline_at >= created_at)
);

COMMENT ON COLUMN request.reference    IS 'Human-readable Nº do Pedido (AKS-YYYY-NNNNN).';
COMMENT ON COLUMN request.client_email IS 'Email captured at creation; snapshot of where it was sent.';
COMMENT ON COLUMN request.status       IS 'Workflow position in the request state machine.';

CREATE INDEX idx_request_status      ON request (status);
CREATE INDEX idx_request_area        ON request (area_id);
CREATE INDEX idx_request_client      ON request (client_id);
CREATE INDEX idx_request_creator     ON request (created_by);
CREATE INDEX idx_request_deadline    ON request (deadline_at);
CREATE INDEX idx_request_area_status ON request (area_id, status);
CREATE INDEX idx_request_destination_type ON request (destination_type);
CREATE INDEX idx_request_recipient_user   ON request (recipient_user_id);


CREATE TABLE request_response (
    id            UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    request_id    UUID         NOT NULL REFERENCES request(id) ON DELETE CASCADE,
    responded_by  UUID         NOT NULL REFERENCES auth_user(id),
    body          TEXT,
    decision      VARCHAR(12)  NOT NULL DEFAULT 'PENDING',
    decided_by    UUID         REFERENCES auth_user(id),
    decided_at    TIMESTAMPTZ,
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT now(),
    CONSTRAINT ck_response_decision
        CHECK (decision IN ('PENDING','ACCEPTED','REJECTED')),
    CONSTRAINT ck_response_decided
        CHECK ( (decision = 'PENDING')
                OR (decided_by IS NOT NULL AND decided_at IS NOT NULL) )
);

COMMENT ON TABLE request_response
    IS 'Client submissions; one row per attempt across rejection loops.';

CREATE INDEX idx_response_request ON request_response (request_id);


CREATE TABLE request_comment (
    id          UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    request_id  UUID         NOT NULL REFERENCES request(id) ON DELETE CASCADE,
    author_id   UUID         NOT NULL REFERENCES auth_user(id),
    body        TEXT         NOT NULL,
    visibility  VARCHAR(12)  NOT NULL DEFAULT 'BOTH',
    edited_by   UUID         REFERENCES auth_user(id),
    edited_at   TIMESTAMPTZ,
    edit_count  INTEGER      NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT now(),
    CONSTRAINT ck_comment_visibility
        CHECK (visibility IN ('BOTH','AKSANTI_ONLY'))
);

COMMENT ON TABLE request_comment
    IS 'Visible commentary that never changes request status (req. #6).';

CREATE INDEX idx_comment_request ON request_comment (request_id);


-- ============================================================
--  8. ATTACHMENTS
-- ============================================================

CREATE TABLE attachment (
    id           UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    request_id   UUID         REFERENCES request(id)          ON DELETE CASCADE,
    response_id  UUID         REFERENCES request_response(id) ON DELETE CASCADE,
    file_name    VARCHAR(255) NOT NULL,
    content_type VARCHAR(120),
    size_bytes   BIGINT,
    storage_key  TEXT         NOT NULL,   -- S3/MinIO object key
    uploaded_by  UUID         REFERENCES auth_user(id),
    updated_by   UUID         REFERENCES auth_user(id),
    updated_at   TIMESTAMPTZ,
    update_count INTEGER      NOT NULL DEFAULT 0,
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT now(),
    CONSTRAINT ck_attachment_parent CHECK (
        (request_id IS NOT NULL)::int + (response_id IS NOT NULL)::int = 1
    )
);

CREATE INDEX idx_attachment_request  ON attachment (request_id);
CREATE INDEX idx_attachment_response ON attachment (response_id);


-- ============================================================
--  9. AUDIT LOG (append-only status history)
-- ============================================================

CREATE TABLE request_audit_log (
    id           UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    request_id   UUID         NOT NULL REFERENCES request(id),
    from_status  VARCHAR(20),
    to_status    VARCHAR(20),
    actor_id     UUID         REFERENCES auth_user(id),
    note         TEXT,
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT now()
);

COMMENT ON TABLE request_audit_log
    IS 'Immutable history of every status transition.';

CREATE INDEX idx_audit_request ON request_audit_log (request_id, created_at);


-- ============================================================
--  10. NOTIFICATIONS
-- ============================================================

CREATE TABLE notification (
    id            UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    recipient_id  UUID         NOT NULL REFERENCES auth_user(id) ON DELETE CASCADE,
    request_id    UUID         REFERENCES request(id) ON DELETE CASCADE,
    type          VARCHAR(32)  NOT NULL,   -- STATUS_CHANGE, DEADLINE, RESPONSE...
    channel       VARCHAR(12)  NOT NULL,
    payload       JSONB,
    is_read       BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT now(),
    read_at       TIMESTAMPTZ,
    CONSTRAINT ck_notification_channel CHECK (channel IN ('IN_APP','EMAIL'))
);

CREATE INDEX idx_notification_unread
    ON notification (recipient_id, created_at)
    WHERE is_read = FALSE;


-- ============================================================
--  11. TRIGGERS
-- ============================================================

-- ---------- TRIGGER 1 — request_audit_log is append-only ----------
CREATE OR REPLACE FUNCTION trg_audit_log_immutable()
RETURNS TRIGGER AS $$
BEGIN
    RAISE EXCEPTION
        'request_audit_log is append-only: % is not permitted', TG_OP
        USING ERRCODE = 'restrict_violation';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER audit_log_no_update
    BEFORE UPDATE ON request_audit_log
    FOR EACH ROW EXECUTE FUNCTION trg_audit_log_immutable();

CREATE TRIGGER audit_log_no_delete
    BEFORE DELETE ON request_audit_log
    FOR EACH ROW EXECUTE FUNCTION trg_audit_log_immutable();


-- ---------- TRIGGER 2 — auto-log every status transition ----------
-- The application sets, per transaction:
--   SET LOCAL arms.current_user_id = '<uuid>';
CREATE OR REPLACE FUNCTION trg_request_status_audit()
RETURNS TRIGGER AS $$
DECLARE
    v_actor UUID;
BEGIN
    BEGIN
        v_actor := NULLIF(current_setting('arms.current_user_id', true), '')::UUID;
    EXCEPTION WHEN others THEN
        v_actor := NULL;
    END;

    IF TG_OP = 'INSERT' THEN
        INSERT INTO request_audit_log (request_id, from_status, to_status, actor_id)
        VALUES (NEW.id, NULL, NEW.status, COALESCE(v_actor, NEW.created_by));
        RETURN NEW;
    END IF;

    IF NEW.status IS DISTINCT FROM OLD.status THEN
        INSERT INTO request_audit_log (request_id, from_status, to_status, actor_id)
        VALUES (NEW.id, OLD.status, NEW.status, v_actor);

        IF NEW.status = 'SENT' AND NEW.sent_at IS NULL THEN
            NEW.sent_at := now();
        ELSIF NEW.status = 'CLOSED' AND NEW.closed_at IS NULL THEN
            NEW.closed_at := now();
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER request_status_audit_ins
    AFTER INSERT ON request
    FOR EACH ROW EXECUTE FUNCTION trg_request_status_audit();

CREATE TRIGGER request_status_audit_upd
    BEFORE UPDATE ON request
    FOR EACH ROW EXECUTE FUNCTION trg_request_status_audit();


-- ---------- TRIGGER 3 — enforce valid state transitions ----------
CREATE OR REPLACE FUNCTION trg_request_valid_transition()
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

-- Runs BEFORE the audit trigger alphabetically? No — Postgres fires
-- BEFORE triggers in name order. 'request_enforce_transition' sorts
-- before 'request_status_audit_upd', so the guard rejects an illegal
-- transition before any audit row is written. Intended.
CREATE TRIGGER request_enforce_transition
    BEFORE UPDATE ON request
    FOR EACH ROW EXECUTE FUNCTION trg_request_valid_transition();


-- ============================================================
--  12. SEED (idempotent)
-- ============================================================

-- ---------- Areas ----------
INSERT INTO area (code, name, is_restricted) VALUES
    ('CONTAB',   'Contabilidade',              FALSE),
    ('AUDIT',    'Auditoria',                  FALSE),
    ('FIN',      'Finanças',                   FALSE),
    ('RH',       'Recursos Humanos',           TRUE),
    ('TECH',     'Tecnologia e Inovação',      FALSE),
    ('CONSULT',  'Consultoria',                FALSE),
    ('EDU',      'Educação e Formação',        FALSE),
    ('LEGAL',    'Jurídico',                   TRUE)
ON CONFLICT (code) DO NOTHING;



/*
-- ---------- Admin user ----------
-- NOTE: password_hash is a NON-FUNCTIONAL placeholder. Replace with a
-- real bcrypt hash (see the Python snippet in chat) or create the admin
-- through the application's registration path.
WITH new_admin AS (
    INSERT INTO auth_user (email, password_hash, user_type, is_admin)
    VALUES (
        'admin@aksanti.xyz',
        '$2b$12$REPLACE_WITH_REAL_BCRYPT_HASH_0000000000000000000000',
        'AKSANTI',
        TRUE
    )
    ON CONFLICT (email) DO NOTHING
    RETURNING id
)
INSERT INTO user_profile (user_id, full_name, locale)
SELECT id, 'Aksanti Administrator', 'pt-PT' FROM new_admin
ON CONFLICT (user_id) DO NOTHING;

-- Grant the admin MANAGER membership on every area
INSERT INTO area_membership (user_id, area_id, role)
SELECT u.id, a.id, 'MANAGER'
FROM auth_user u
CROSS JOIN area a
WHERE u.email = 'admin@aksanti.xyz'
ON CONFLICT (user_id, area_id) DO NOTHING;


-- ---------- Sample client + contact ----------
WITH new_client AS (
    INSERT INTO client (name, primary_email)
    VALUES ('Cliente Exemplo, Lda.', 'geral@cliente-exemplo.co.ao')
    ON CONFLICT DO NOTHING
    RETURNING id
)
INSERT INTO client_contact (client_id, email, full_name)
SELECT id, 'financas@cliente-exemplo.co.ao', 'Maria Financeira'
FROM new_client
ON CONFLICT (client_id, email) DO NOTHING;


-- ---------- Verification ----------
SELECT 'areas'    AS entity, count(*) FROM area
UNION ALL SELECT 'users',    count(*) FROM auth_user
UNION ALL SELECT 'clients',  count(*) FROM client
UNION ALL SELECT 'contacts', count(*) FROM client_contact;



*/
