#!/bin/sh
set -e

# Named volumes are root-owned by default on first mount. Ensure www-data can
# read/write the storage tree (DI cache, uploads, logs, etc.).
mkdir -p /var/www/html/storage/cache/di \
         /var/www/html/storage/logs \
         /var/www/html/storage/uploads

# The compiled DI container lives in the persistent volume: drop it so an
# upgraded image never runs against a container compiled for older code.
rm -rf /var/www/html/storage/cache/di/*

chown -R www-data:www-data /var/www/html/storage
chmod 750 /var/www/html/storage

exec "$@"
