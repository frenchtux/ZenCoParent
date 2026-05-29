-- SQLite: ADD COLUMN IF NOT EXISTS not supported; plain ADD COLUMN (safe, migrations run once)
ALTER TABLE app_license ADD COLUMN customer_email TEXT;
ALTER TABLE app_license ADD COLUMN expires_at     TEXT;
