CREATE TABLE IF NOT EXISTS user_tenant_access (
    id         TEXT    PRIMARY KEY,
    user_id    TEXT    NOT NULL REFERENCES users(id)   ON DELETE CASCADE,
    tenant_id  TEXT    NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    role       TEXT    NOT NULL DEFAULT 'parent' CHECK (role IN ('parent', 'child', 'admin')),
    is_active  INTEGER NOT NULL DEFAULT 1,
    created_at TEXT    NOT NULL DEFAULT (datetime('now')),
    UNIQUE (user_id, tenant_id)
);
CREATE INDEX IF NOT EXISTS idx_uta_user_id   ON user_tenant_access(user_id);
CREATE INDEX IF NOT EXISTS idx_uta_tenant_id ON user_tenant_access(tenant_id);
