-- SaaS plans: defines available subscription tiers and which modules they unlock
CREATE TABLE plans (
    id                       UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    name                     TEXT        NOT NULL UNIQUE,
    display_name             TEXT        NOT NULL,
    description              TEXT        NOT NULL DEFAULT '',
    price_monthly_cents      INT         NOT NULL DEFAULT 0,
    price_yearly_cents       INT         NOT NULL DEFAULT 0,
    stripe_price_id_monthly  TEXT,
    stripe_price_id_yearly   TEXT,
    modules                  JSONB       NOT NULL DEFAULT '{}',
    is_active                BOOLEAN     NOT NULL DEFAULT TRUE,
    created_at               TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at               TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Seed with built-in plans
INSERT INTO plans (name, display_name, description, price_monthly_cents, price_yearly_cents, modules) VALUES
('free',    'Gratuit',  'Accès de base : enfants et calendrier uniquement.',
 0, 0,
 '{"expenses":false,"photos":false,"messages":false,"medical":false}'),
('family',  'Famille',  'Tous les modules pour une famille.',
 799, 7990,
 '{"expenses":true,"photos":true,"messages":true,"medical":true}'),
('premium', 'Premium',  'Tous les modules + stockage étendu.',
 1299, 12990,
 '{"expenses":true,"photos":true,"messages":true,"medical":true}');
