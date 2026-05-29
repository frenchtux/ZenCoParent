CREATE TABLE tenant_settings (
    id         UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id  UUID        NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    key        TEXT        NOT NULL,
    value      TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (tenant_id, key)
);
CREATE INDEX idx_tenant_settings_tenant ON tenant_settings(tenant_id);
