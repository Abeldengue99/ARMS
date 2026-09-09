-- ============================================================================
-- ARMS Migration: Adicionar Módulo de Projetos
-- ============================================================================
-- Criado em: 2026-09-09
-- Autor: AntiGravity
-- Descrição: Cria a tabela de projetos (arms.project) e associa-a aos pedidos (arms.request).
-- ============================================================================

-- 1. Criar a tabela project
CREATE TABLE arms.project (
    id              UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    name            VARCHAR(200) NOT NULL,
    client_id       UUID         REFERENCES arms.client(id) ON DELETE SET NULL,
    owner_user_id   UUID         REFERENCES arms.auth_user(id) ON DELETE SET NULL,
    is_active       BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT now(),
    -- Um projeto deve estar obrigatoriamente associado a um cliente OU a um membro interno.
    CONSTRAINT ck_project_association CHECK (
        client_id IS NOT NULL OR owner_user_id IS NOT NULL
    )
);

-- Comentários na tabela e colunas (boas práticas para manutenção)
COMMENT ON TABLE arms.project IS 'Projetos que agrupam pedidos. Cada projeto está associado a um cliente ou membro interno.';
COMMENT ON COLUMN arms.project.client_id IS 'Cliente externo associado ao projeto (opcional se owner_user_id preenchido).';
COMMENT ON COLUMN arms.project.owner_user_id IS 'Membro interno Aksanti associado ao projeto (opcional se client_id preenchido).';

-- Índices de performance para a tabela project
CREATE INDEX idx_project_client    ON arms.project (client_id);
CREATE INDEX idx_project_owner     ON arms.project (owner_user_id);
CREATE INDEX idx_project_active    ON arms.project (is_active);

-- 2. Adicionar o projeto ao pedido
ALTER TABLE arms.request
    ADD COLUMN project_id UUID REFERENCES arms.project(id) ON DELETE SET NULL;

-- Índice para melhorar a pesquisa por projeto na tabela de pedidos
CREATE INDEX idx_request_project ON arms.request (project_id);
