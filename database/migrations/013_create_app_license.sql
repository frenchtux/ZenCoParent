-- SaaS-only table: tracks installation + activation key
CREATE TABLE app_license (
    id               UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    installation_key TEXT        NOT NULL UNIQUE,
    activation_key   TEXT,
    installed_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    activated_at     TIMESTAMPTZ,
    is_active        BOOLEAN     NOT NULL DEFAULT FALSE,
    instance_id      TEXT        NOT NULL DEFAULT ''
);
