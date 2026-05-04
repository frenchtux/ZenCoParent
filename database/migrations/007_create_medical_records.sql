CREATE TABLE medical_records (
    id           UUID    PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id    UUID    NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    child_id     UUID    NOT NULL REFERENCES children(id) ON DELETE CASCADE,
    event_id     UUID    REFERENCES events(id) ON DELETE SET NULL,
    report       TEXT    NOT NULL,
    practitioner TEXT,
    recorded_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_by   UUID    REFERENCES users(id) ON DELETE SET NULL,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_medical_records_tenant_id  ON medical_records(tenant_id);
CREATE INDEX idx_medical_records_child_id   ON medical_records(child_id);
CREATE INDEX idx_medical_records_event_id   ON medical_records(event_id);
CREATE INDEX idx_medical_records_recorded_at ON medical_records(recorded_at);
