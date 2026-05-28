#!/bin/sh
set -e

# â”€â”€ Fix storage directory permissions â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
# The named volume mounted at /var/www/html/storage may be root-owned on first
# start. Ensure www-data can read/write (this runs as root before privilege drop).
mkdir -p /var/www/html/storage
chown -R www-data:www-data /var/www/html/storage
chmod 750 /var/www/html/storage

echo "[entrypoint] Running SQLite migrations..."
su-exec www-data php /var/www/html/database/migrations/migrate_sqlite.php

echo "[entrypoint] Seeding default admin (tenant: zencoparent)..."
su-exec www-data php /var/www/html/seed_admin.php || echo "[entrypoint] Seed warning (non-fatal, continuing...)"

echo "[entrypoint] Starting php-fpm..."
exec "$@"
