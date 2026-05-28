-- SQLite override for 012_expand_event_types.sql
-- SQLite does not support ALTER TABLE DROP/ADD CONSTRAINT.
-- Use the table-recreation pattern instead.

CREATE TABLE events_new (
    id           TEXT    PRIMARY KEY,
    tenant_id    TEXT    NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    child_id     TEXT    REFERENCES children(id) ON DELETE SET NULL,
    title        TEXT    NOT NULL,
    description  TEXT,
    type         TEXT    NOT NULL CHECK (type IN ('custody', 'activity', 'medical', 'rendezvous', 'activite', 'vacances', 'autre')),
    start_at     TEXT    NOT NULL,
    end_at       TEXT    NOT NULL,
    all_day      INTEGER NOT NULL DEFAULT 0,
    created_by   TEXT    REFERENCES users(id) ON DELETE SET NULL,
    created_at   TEXT    NOT NULL DEFAULT (datetime('now')),
    updated_at   TEXT    NOT NULL DEFAULT (datetime('now'))
);

INSERT INTO events_new SELECT * FROM events;

DROP TABLE events;

ALTER TABLE events_new RENAME TO events;

CREATE INDEX idx_events_tenant_id ON events(tenant_id);
CREATE INDEX idx_events_child_id  ON events(child_id);
CREATE INDEX idx_events_type      ON events(type);
CREATE INDEX idx_events_start_at  ON events(start_at);
