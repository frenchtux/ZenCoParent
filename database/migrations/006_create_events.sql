CREATE TABLE events (
    id           UUID    PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id    UUID    NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    child_id     UUID    REFERENCES children(id) ON DELETE SET NULL,
    title        TEXT    NOT NULL,
    description  TEXT,
    type         TEXT    NOT NULL CHECK (type IN ('custody', 'activity', 'medical')),
    start_at     TIMESTAMPTZ NOT NULL,
    end_at       TIMESTAMPTZ NOT NULL,
    all_day      BOOLEAN NOT NULL DEFAULT FALSE,
    created_by   UUID    REFERENCES users(id) ON DELETE SET NULL,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_events_tenant_id ON events(tenant_id);
CREATE INDEX idx_events_child_id  ON events(child_id);
CREATE INDEX idx_events_type      ON events(type);
CREATE INDEX idx_events_start_at  ON events(start_at);
