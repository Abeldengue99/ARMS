-- Sessao PHP partilhada para ambientes com Docker/Coolify.
-- Seguro para executar em producao: apenas cria tabela/indice se nao existirem.

CREATE SCHEMA IF NOT EXISTS arms;

CREATE TABLE IF NOT EXISTS arms.php_session (
    id VARCHAR(128) PRIMARY KEY,
    data TEXT NOT NULL DEFAULT '',
    last_activity TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_php_session_last_activity
    ON arms.php_session (last_activity);

COMMENT ON TABLE arms.php_session IS 'PHP session storage shared by all web containers.';
