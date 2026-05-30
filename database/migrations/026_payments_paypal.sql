-- Migration 026 : ajout du support PayPal sur la table payments
-- Les colonnes stripe_* restent pour les paiements Stripe existants.

ALTER TABLE payments ADD COLUMN IF NOT EXISTS paypal_order_id VARCHAR(255) NULL;

CREATE INDEX IF NOT EXISTS idx_payments_paypal_order_id
    ON payments(paypal_order_id)
    WHERE paypal_order_id IS NOT NULL;
