CREATE TABLE medical_attachments (
    id          UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   UUID        NOT NULL REFERENCES tenants(id)          ON DELETE CASCADE,
    record_id   UUID        NOT NULL REFERENCES medical_records(id)  ON DELETE CASCADE,
    filename    TEXT        NOT NULL,
    mime_type   TEXT        NOT NULL,
    size_bytes  INTEGER     NOT NULL DEFAULT 0,
    storage_key TEXT        NOT NULL,
    uploaded_by UUID        REFERENCES users(id) ON DELETE SET NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_medical_attachments_record ON medical_attachments(record_id);
CREATE INDEX idx_medical_attachments_tenant ON medical_attachments(tenant_id);
