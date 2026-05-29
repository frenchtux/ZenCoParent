-- Licence v2 : ajout des métadonnées client et date d'expiration.
-- La colonne activation_key stocke désormais le token Ed25519 signé (format v2).
-- Les lignes existantes (v1) sont simplement invalidées au premier appel activate().

ALTER TABLE app_license ADD COLUMN IF NOT EXISTS customer_email VARCHAR(255);
ALTER TABLE app_license ADD COLUMN IF NOT EXISTS expires_at     TIMESTAMPTZ;
