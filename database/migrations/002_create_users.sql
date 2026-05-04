CREATE TABLE users (
    id                  UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id           UUID        NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    email               TEXT        NOT NULL,
    password_hash       TEXT,
    first_name          TEXT        NOT NULL,
    last_name           TEXT        NOT NULL,
    phone               TEXT,
    address             TEXT,
    role                TEXT        NOT NULL DEFAULT 'parent' CHECK (role IN ('parent', 'child', 'admin')),
    is_active           BOOLEAN     NOT NULL DEFAULT TRUE,
    email_verified_at   TIMESTAMPTZ,
    last_login_at       TIMESTAMPTZ,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (tenant_id, email)
);
CREATE INDEX idx_users_tenant_id   ON users(tenant_id);
CREATE INDEX idx_users_email       ON users(email);
CREATE INDEX idx_users_role        ON users(role);
