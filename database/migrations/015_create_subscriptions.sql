-- One subscription per tenant; tracks Stripe state and trial window
CREATE TABLE subscriptions (
    id                     UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id              UUID        NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    plan_id                UUID        REFERENCES plans(id),
    stripe_customer_id     TEXT,
    stripe_subscription_id TEXT        UNIQUE,
    status                 TEXT        NOT NULL DEFAULT 'trial'
                                       CHECK (status IN ('trial','active','past_due','cancelled','expired')),
    billing_interval       TEXT        CHECK (billing_interval IN ('monthly','yearly')),
    current_period_start   TIMESTAMPTZ,
    current_period_end     TIMESTAMPTZ,
    trial_ends_at          TIMESTAMPTZ,
    cancelled_at           TIMESTAMPTZ,
    created_at             TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at             TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX idx_subscriptions_tenant ON subscriptions(tenant_id);
CREATE INDEX idx_subscriptions_stripe ON subscriptions(stripe_subscription_id);
CREATE INDEX idx_subscriptions_status ON subscriptions(status);
