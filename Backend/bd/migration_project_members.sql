-- ============================================================
-- MIGRAÇÃO: Tabela de Membros de Projeto (N:M)
-- Permite associar múltiplos membros da equipa a um projeto.
-- ============================================================

-- 1. Criar a tabela de ligação project_member
CREATE TABLE IF NOT EXISTS arms.project_member (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    project_id UUID NOT NULL REFERENCES arms.project(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES arms.auth_user(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (project_id, user_id)
);

CREATE INDEX IF NOT EXISTS idx_project_member_project ON arms.project_member(project_id);
CREATE INDEX IF NOT EXISTS idx_project_member_user ON arms.project_member(user_id);

-- 2. Migrar dados existentes de owner_user_id para a nova tabela
INSERT INTO arms.project_member (project_id, user_id)
SELECT id, owner_user_id
FROM arms.project
WHERE owner_user_id IS NOT NULL
ON CONFLICT (project_id, user_id) DO NOTHING;

-- 3. Remover a coluna antiga (owner_user_id) da tabela project
ALTER TABLE arms.project DROP COLUMN IF EXISTS owner_user_id;

-- 4. Garantir que a coluna description existe
ALTER TABLE arms.project ADD COLUMN IF NOT EXISTS description TEXT;
