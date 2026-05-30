-- Migration 026 SQLite : ajout de paypal_order_id sur la table payments
-- SQLite ne supporte pas IF NOT EXISTS sur ALTER TABLE ADD COLUMN.
-- On crée la colonne directement — SQLite lèvera une erreur si elle existe déjà,
-- ce qui est acceptable en contexte de test (base fraîche à chaque run).
ALTER TABLE payments ADD COLUMN paypal_order_id VARCHAR(255) NULL;
CREATE INDEX IF NOT EXISTS idx_payments_paypal_order_id ON payments(paypal_order_id);
