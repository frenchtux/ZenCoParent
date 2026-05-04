CREATE TABLE expenses (
    id           UUID            PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id    UUID            NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    paid_by      UUID            NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    amount       NUMERIC(10,2)   NOT NULL CHECK (amount > 0),
    description  TEXT            NOT NULL,
    category     TEXT,
    split_ratio  JSONB           NOT NULL DEFAULT '{}',
    date         DATE            NOT NULL,
    created_at   TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_expenses_tenant_id ON expenses(tenant_id);
CREATE INDEX idx_expenses_paid_by   ON expenses(paid_by);
CREATE INDEX idx_expenses_date      ON expenses(date);
