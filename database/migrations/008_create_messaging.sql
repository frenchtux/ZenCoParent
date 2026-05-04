CREATE TABLE threads (
    id          UUID    PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   UUID    NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    type        TEXT    NOT NULL CHECK (type IN ('parents', 'family')),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_threads_tenant_id ON threads(tenant_id);

CREATE TABLE thread_participants (
    thread_id   UUID    NOT NULL REFERENCES threads(id) ON DELETE CASCADE,
    user_id     UUID    NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    joined_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (thread_id, user_id)
);
CREATE INDEX idx_thread_participants_user_id ON thread_participants(user_id);

CREATE TABLE messages (
    id          UUID    PRIMARY KEY DEFAULT gen_random_uuid(),
    thread_id   UUID    NOT NULL REFERENCES threads(id) ON DELETE CASCADE,
    tenant_id   UUID    NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    sender_id   UUID    NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    content     TEXT    NOT NULL,
    read_at     TIMESTAMPTZ,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_messages_thread_id  ON messages(thread_id);
CREATE INDEX idx_messages_sender_id  ON messages(sender_id);
CREATE INDEX idx_messages_created_at ON messages(created_at);
