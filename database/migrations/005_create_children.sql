CREATE TABLE children (
    id           UUID    PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id    UUID    NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    first_name   TEXT    NOT NULL,
    last_name    TEXT    NOT NULL,
    birthdate    DATE,
    medical_info JSONB   NOT NULL DEFAULT '{}',
    school_info  JSONB   NOT NULL DEFAULT '{}',
    created_by   UUID    REFERENCES users(id) ON DELETE SET NULL,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_children_tenant_id ON children(tenant_id);
