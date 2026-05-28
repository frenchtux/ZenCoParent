#!/bin/sh
set -e

# â”€â”€ Fix storage directory permissions â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
# Named volumes are root-owned by default on first mount. Ensure www-data can
# read/write the storage tree (DI cache, uploads, logs, etc.).
mkdir -p /var/www/html/storage/cache/di \
         /var/www/html/storage/logs \
         /var/www/html/storage/uploads
chown -R www-data:www-data /var/www/html/storage
chmod 750 /var/www/html/storage

echo "[entrypoint] Starting php-fpm..."
exec "$@"
