#!/bin/sh
set -e

echo "[entrypoint] Running SQLite migrations..."
php /var/www/html/database/migrations/migrate_sqlite.php

echo "[entrypoint] Starting php-fpm..."
exec "$@"
