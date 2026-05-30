-- Migration 027 : table des settings système (sans FK sur tenants)
-- Utilisée pour les paramètres globaux : OAuth, PayPal, app_name, rate limit, etc.

CREATE TABLE IF NOT EXISTS app_settings (
    key        TEXT        PRIMARY KEY,
    value      TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
