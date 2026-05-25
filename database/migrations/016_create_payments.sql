-- Immutable payment records (installation key purchases + subscription invoices)
CREATE TABLE payments (
    id                        UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id                 UUID        REFERENCES tenants(id) ON DELETE SET NULL,
    stripe_payment_intent_id  TEXT        UNIQUE,
    stripe_invoice_id         TEXT        UNIQUE,
    stripe_session_id         TEXT        UNIQUE,
    type                      TEXT        NOT NULL
                                          CHECK (type IN ('installation_key','subscription')),
    amount_cents              INT         NOT NULL,
    currency                  TEXT        NOT NULL DEFAULT 'eur',
    status                    TEXT        NOT NULL DEFAULT 'pending'
                                          CHECK (status IN ('pending','succeeded','failed','refunded')),
    metadata                  JSONB       NOT NULL DEFAULT '{}',
    paid_at                   TIMESTAMPTZ,
    created_at                TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_payments_tenant   ON payments(tenant_id);
CREATE INDEX idx_payments_status   ON payments(status);
CREATE INDEX idx_payments_type     ON payments(type);
