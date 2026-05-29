CREATE TABLE IF NOT EXISTS medical_attachments (
    id          TEXT    PRIMARY KEY,
    tenant_id   TEXT    NOT NULL REFERENCES tenants(id)          ON DELETE CASCADE,
    record_id   TEXT    NOT NULL REFERENCES medical_records(id)  ON DELETE CASCADE,
    filename    TEXT    NOT NULL,
    mime_type   TEXT    NOT NULL,
    size_bytes  INTEGER NOT NULL DEFAULT 0,
    storage_key TEXT    NOT NULL,
    uploaded_by TEXT    REFERENCES users(id) ON DELETE SET NULL,
    created_at  TEXT    NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_medical_attachments_record ON medical_attachments(record_id);
CREATE INDEX IF NOT EXISTS idx_medical_attachments_tenant ON medical_attachments(tenant_id);
