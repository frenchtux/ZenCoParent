-- License hardening: revocation support + machine fingerprint anti-cloning
ALTER TABLE app_license ADD COLUMN IF NOT EXISTS revoked_at          TIMESTAMPTZ;
ALTER TABLE app_license ADD COLUMN IF NOT EXISTS machine_fingerprint VARCHAR(64);
