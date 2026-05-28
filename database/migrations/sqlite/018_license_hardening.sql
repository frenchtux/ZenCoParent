-- SQLite: ADD COLUMN IF NOT EXISTS not supported; use plain ADD COLUMN
-- SQLite ignores duplicate ADD COLUMN errors gracefully when wrapped in transactions,
-- but to be safe we just add without IF NOT EXISTS (will fail if already exists).
-- Since migrations are tracked and run once, this is safe.
ALTER TABLE app_license ADD COLUMN revoked_at          TEXT;
ALTER TABLE app_license ADD COLUMN machine_fingerprint TEXT;
