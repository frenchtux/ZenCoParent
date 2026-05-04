CREATE TABLE photos (
    id           UUID    PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id    UUID    NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    child_id     UUID    REFERENCES children(id) ON DELETE SET NULL,
    storage_key  TEXT    NOT NULL,
    filename     TEXT    NOT NULL,
    mime_type    TEXT    NOT NULL,
    size_bytes   INTEGER,
    caption      TEXT,
    created_by   UUID    REFERENCES users(id) ON DELETE SET NULL,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_photos_tenant_id ON photos(tenant_id);
CREATE INDEX idx_photos_child_id  ON photos(child_id);
