-- Table de jointure : un utilisateur peut accéder à plusieurs tenants
-- Remplace la contrainte UNIQUE(tenant_id, email) sur users par cette table
-- pour les cas multi-tenants. Pour les tenants mono-user, rien ne change.
CREATE TABLE user_tenant_access (
    id         UUID    PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id    UUID    NOT NULL REFERENCES users(id)   ON DELETE CASCADE,
    tenant_id  UUID    NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    role       TEXT    NOT NULL DEFAULT 'parent' CHECK (role IN ('parent', 'child', 'admin')),
    is_active  BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (user_id, tenant_id)
);
CREATE INDEX idx_uta_user_id   ON user_tenant_access(user_id);
CREATE INDEX idx_uta_tenant_id ON user_tenant_access(tenant_id);
