#!/usr/bin/env python3
"""
ZenCoParent -- Setup Wizard
Génère les fichiers docker-compose et .env selon le mode d'installation choisi.

Usage :
    python scripts/setup.py
"""

import os
import re
import secrets
import string
import sys
from pathlib import Path

ROOT = Path(__file__).parent.parent

# -- Helpers -------------------------------------------------------------------

def secret(n: int = 48) -> str:
    alphabet = string.ascii_letters + string.digits
    return ''.join(secrets.choice(alphabet) for _ in range(n))

def ask(prompt: str, default: str = '', required: bool = False) -> str:
    while True:
        suffix = f' [{default}]' if default else ''
        val = input(f'  {prompt}{suffix}: ').strip()
        if not val:
            val = default
        if required and not val:
            print('    !  Ce champ est obligatoire.')
            continue
        return val

def ask_int(prompt: str, default: int, min_val: int = 1, max_val: int = 65535) -> int:
    while True:
        raw = ask(prompt, str(default))
        try:
            val = int(raw)
            if min_val <= val <= max_val:
                return val
            print(f'    !  Valeur entre {min_val} et {max_val}.')
        except ValueError:
            print('    !  Entier attendu.')

def ask_choice(prompt: str, choices: list[str], default: str = '') -> str:
    label = '/'.join(f'[{c}]' if c == default else c for c in choices)
    while True:
        val = ask(f'{prompt} ({label})', default).lower()
        if val in [c.lower() for c in choices]:
            return val
        print(f'    !  Choisissez parmi : {", ".join(choices)}')

def hr(title: str = '') -> None:
    width = 60
    if title:
        pad = (width - len(title) - 2) // 2
        print('\n' + '-' * pad + f' {title} ' + '-' * pad)
    else:
        print('\n' + '-' * width)

def banner() -> None:
    print("""
+══════════════════════════════════════════════════════════+
|          ZenCoParent -- Assistant de configuration        |
|          Generation docker-compose + .env                |
+══════════════════════════════════════════════════════════+
""")

# -- Community -----------------------------------------------------------------

def setup_community() -> None:
    hr('Community -- configuration')

    app_url  = ask('URL publique de l\'application', 'http://localhost')
    app_port = ask_int('Port HTTP exposé', 80)

    # Ajuste STORAGE_URL selon l'URL
    storage_url = app_url.rstrip('/') + '/storage'

    hr('Email / SMTP (laisser vide pour désactiver)')
    mail_host = ask('MAIL_HOST', '')
    mail_port = ask_int('MAIL_PORT', 587) if mail_host else 587
    mail_enc  = ask_choice('MAIL_ENCRYPTION', ['tls', 'ssl', 'none'], 'tls') if mail_host else 'tls'
    mail_user = ask('MAIL_USERNAME', '') if mail_host else ''
    mail_pass = ask('MAIL_PASSWORD', '') if mail_host else ''
    mail_from = ask('MAIL_FROM_ADDRESS', 'noreply@zencoparent.com') if mail_host else 'noreply@zencoparent.com'
    mail_name = ask('MAIL_FROM_NAME', 'ZenCoParent') if mail_host else 'ZenCoParent'

    hr('Secrets (auto-generes -- modifiables)')
    app_secret  = ask('APP_SECRET', secret(64))
    jwt_secret  = ask('JWT_SECRET',  secret(64))
    csrf_secret = ask('CSRF_SECRET', secret(48))

    # -- Generation docker-compose ----------------------------------------------
    compose = f"""\
# -----------------------------------------------------------------------------
# ZenCoParent -- Community edition  (genere par scripts/setup.py)
# Single-tenant · SQLite · sans services externes
# -----------------------------------------------------------------------------

services:

  nginx:
    build:
      context: "https://github.com/frenchtux/ZenCoParent.git#main:docker/nginx"
      dockerfile: Dockerfile
    restart: unless-stopped
    ports:
      - "{app_port}:80"
    depends_on:
      - php
    volumes:
      - app_code:/var/www/html:ro
    networks:
      - zenco_net

  php:
    build:
      context: "https://github.com/frenchtux/ZenCoParent.git#main"
      dockerfile: docker/php/Dockerfile.community
    restart: unless-stopped
    volumes:
      - app_code:/var/www/html
      - zenco_storage:/var/www/html/storage
    environment:
      APP_NAME: ZenCoParent
      APP_ENV: production
      APP_MODE: community
      APP_SECRET: "{app_secret}"
      APP_URL: "{app_url}"
      APP_DEBUG: "false"
      DB_CONNECTION: sqlite
      DB_FILE: /var/www/html/storage/database.sqlite
      JWT_SECRET: "{jwt_secret}"
      JWT_EXPIRY: "3600"
      JWT_REFRESH_EXPIRY: "2592000"
      CSRF_SECRET: "{csrf_secret}"
      STORAGE_PATH: /var/www/html/storage
      STORAGE_URL: "{storage_url}"
      RATE_LIMIT_REQUESTS: "60"
      RATE_LIMIT_WINDOW: "60"
      MAIL_HOST: "{mail_host}"
      MAIL_PORT: "{mail_port}"
      MAIL_ENCRYPTION: "{mail_enc}"
      MAIL_USERNAME: "{mail_user}"
      MAIL_PASSWORD: "{mail_pass}"
      MAIL_FROM_ADDRESS: "{mail_from}"
      MAIL_FROM_NAME: "{mail_name}"
    networks:
      - zenco_net

networks:
  zenco_net:
    driver: bridge

volumes:
  app_code:
  zenco_storage:
"""

    out = ROOT / 'docker-compose.community.generated.yml'
    out.write_text(compose, encoding='utf-8')
    print(f'\n  [OK]  {out.relative_to(ROOT)}')
    print(f"""
  Démarrer :
    docker compose -f {out.name} up -d

  Premier admin : admin@zencoparent.local / Admin1234!
  (à changer dès le premier login)
""")


# -- SaaS ----------------------------------------------------------------------

def setup_saas() -> None:
    hr('SaaS -- configuration générale')

    app_url  = ask('URL publique de l\'application', 'http://localhost')
    app_port = ask_int('Port HTTP exposé', 8061)

    hr('Base de données PostgreSQL')
    db_host = ask('DB_HOST',     'postgres')
    db_port = ask_int('DB_PORT', 5432)
    db_name = ask('DB_DATABASE', 'zencoparent')
    db_user = ask('DB_USERNAME', 'zencoparent')
    db_pass = ask('DB_PASSWORD', secret(32), required=True)

    hr('Redis')
    redis_host = ask('REDIS_HOST', 'redis')
    redis_port = ask_int('REDIS_PORT', 6379)
    redis_pass = ask('REDIS_PASSWORD', '')

    hr('MinIO (stockage fichiers)')
    minio_endpoint = ask('MINIO_ENDPOINT (interne Docker)', 'http://minio:9000')
    minio_public   = ask('MINIO_PUBLIC_URL (accessible navigateur)', f'{app_url.rstrip("/")}:9000' if 'localhost' in app_url else app_url.rstrip('/') + '/minio')
    minio_bucket   = ask('MINIO_BUCKET', 'zencoparent')
    minio_user     = ask('MINIO_ACCESS_KEY', 'minioadmin')
    minio_pass     = ask('MINIO_SECRET_KEY', secret(32), required=True)
    minio_region   = ask('MINIO_REGION', 'us-east-1')

    hr('Stripe (paiements -- laisser vide pour désactiver)')
    stripe_key    = ask('STRIPE_SECRET_KEY', '')
    stripe_price  = ask('STRIPE_PRICE_ID_LICENSE', '') if stripe_key else ''
    stripe_webhook= ask('STRIPE_WEBHOOK_SECRET', '') if stripe_key else ''
    license_price = ask('LICENSE_PRICE_EUR (ex: 150)', '150') if stripe_key else '150'

    hr('Email / SMTP (laisser vide pour désactiver)')
    mail_host = ask('MAIL_HOST', '')
    mail_port = ask_int('MAIL_PORT', 587) if mail_host else 587
    mail_enc  = ask_choice('MAIL_ENCRYPTION', ['tls', 'ssl', 'none'], 'tls') if mail_host else 'tls'
    mail_user = ask('MAIL_USERNAME', '') if mail_host else ''
    mail_pass = ask('MAIL_PASSWORD', '') if mail_host else ''
    mail_from = ask('MAIL_FROM_ADDRESS', 'noreply@zencoparent.com') if mail_host else 'noreply@zencoparent.com'
    mail_name = ask('MAIL_FROM_NAME', 'ZenCoParent') if mail_host else 'ZenCoParent'

    hr('Secrets (auto-generes -- modifiables)')
    app_secret      = ask('APP_SECRET',          secret(64))
    jwt_secret      = ask('JWT_SECRET',           secret(64))
    csrf_secret     = ask('CSRF_SECRET',          secret(48))
    license_master  = ask('LICENSE_MASTER_KEY',   secret(56))

    # -- .env.saas --------------------------------------------------------------
    env_content = f"""\
APP_NAME=ZenCoParent
APP_ENV=production
APP_MODE=saas
APP_SECRET={app_secret}
APP_URL={app_url}
APP_DEBUG=false

DB_CONNECTION=pgsql
DB_HOST={db_host}
DB_PORT={db_port}
DB_DATABASE={db_name}
DB_USERNAME={db_user}
DB_PASSWORD={db_pass}

REDIS_HOST={redis_host}
REDIS_PORT={redis_port}
REDIS_PASSWORD={redis_pass}

JWT_SECRET={jwt_secret}
JWT_EXPIRY=3600
JWT_REFRESH_EXPIRY=2592000

CSRF_SECRET={csrf_secret}

STORAGE_PATH=/var/www/html/storage
STORAGE_URL={app_url.rstrip('/')}/storage

MINIO_ENDPOINT={minio_endpoint}
MINIO_PUBLIC_URL={minio_public}
MINIO_ACCESS_KEY={minio_user}
MINIO_SECRET_KEY={minio_pass}
MINIO_BUCKET={minio_bucket}
MINIO_REGION={minio_region}

RATE_LIMIT_REQUESTS=60
RATE_LIMIT_WINDOW=60

LICENSE_MASTER_KEY={license_master}

STRIPE_SECRET_KEY={stripe_key}
STRIPE_PRICE_ID_LICENSE={stripe_price}
STRIPE_WEBHOOK_SECRET={stripe_webhook}
LICENSE_PRICE_EUR={license_price}

MAIL_HOST={mail_host}
MAIL_PORT={mail_port}
MAIL_ENCRYPTION={mail_enc}
MAIL_USERNAME={mail_user}
MAIL_PASSWORD={mail_pass}
MAIL_FROM_ADDRESS={mail_from}
MAIL_FROM_NAME={mail_name}
"""

    # -- docker-compose ----------------------------------------------------------
    compose = f"""\
# -----------------------------------------------------------------------------
# ZenCoParent -- SaaS edition  (genere par scripts/setup.py)
# PostgreSQL · Redis · MinIO · multi-tenant
# -----------------------------------------------------------------------------

services:

  nginx:
    image: nginx:1.27-alpine
    restart: unless-stopped
    ports:
      - "{app_port}:80"
    depends_on:
      - php
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
      - .:/var/www/html:ro
    networks:
      - zencoparent_net

  php:
    build:
      context: ./docker/php
      dockerfile: Dockerfile
    restart: unless-stopped
    volumes:
      - .:/var/www/html
      - ./docker/php/php.ini:/usr/local/etc/php/conf.d/custom.ini:ro
      - zencoparent_storage:/var/www/html/storage
    env_file:
      - .env.saas.generated
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
      minio-init:
        condition: service_completed_successfully
      seed:
        condition: service_completed_successfully
    networks:
      - zencoparent_net

  seed:
    build:
      context: ./docker/php
      dockerfile: Dockerfile
    volumes:
      - .:/var/www/html
      - ./docker/php/php.ini:/usr/local/etc/php/conf.d/custom.ini:ro
    env_file:
      - .env.saas.generated
    command: ["php", "/var/www/html/seed_admin_saas.php"]
    depends_on:
      migrate:
        condition: service_completed_successfully
    restart: "no"
    networks:
      - zencoparent_net

  migrate:
    build:
      context: ./docker/php
      dockerfile: Dockerfile
    volumes:
      - .:/var/www/html
      - ./docker/php/php.ini:/usr/local/etc/php/conf.d/custom.ini:ro
    env_file:
      - .env.saas.generated
    command: ["php", "/var/www/html/database/migrations/migrate.php"]
    depends_on:
      postgres:
        condition: service_healthy
    restart: "no"
    networks:
      - zencoparent_net

  postgres:
    image: postgres:16-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: {db_name}
      POSTGRES_USER: {db_user}
      POSTGRES_PASSWORD: {db_pass}
    volumes:
      - zencoparent_pgdata:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U {db_user} -d {db_name}"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - zencoparent_net

  redis:
    image: redis:7-alpine
    restart: unless-stopped
    command: redis-server{' --requirepass ' + redis_pass if redis_pass else ''} --appendonly yes
    volumes:
      - zencoparent_redis:/data
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - zencoparent_net

  minio:
    image: minio/minio:latest
    restart: unless-stopped
    command: server /data --console-address :9001
    ports:
      - "9000:9000"
      - "9001:9001"
    environment:
      MINIO_ROOT_USER: {minio_user}
      MINIO_ROOT_PASSWORD: {minio_pass}
    volumes:
      - zencoparent_minio:/data
    healthcheck:
      test: ["CMD-SHELL", "curl -sf http://localhost:9000/minio/health/live"]
      interval: 15s
      timeout: 5s
      retries: 5
      start_period: 20s
    networks:
      - zencoparent_net

  minio-init:
    image: minio/mc:latest
    depends_on:
      minio:
        condition: service_healthy
    entrypoint: >
      /bin/sh -c "
        mc alias set local http://minio:9000 {minio_user} {minio_pass} &&
        mc mb --ignore-existing local/{minio_bucket} &&
        echo 'MinIO bucket ready.'
      "
    restart: "no"
    networks:
      - zencoparent_net

networks:
  zencoparent_net:
    driver: bridge

volumes:
  zencoparent_pgdata:
  zencoparent_redis:
  zencoparent_minio:
  zencoparent_storage:
"""

    env_out     = ROOT / '.env.saas.generated'
    compose_out = ROOT / 'docker-compose.saas.generated.yml'

    env_out.write_text(env_content, encoding='utf-8')
    compose_out.write_text(compose, encoding='utf-8')

    print(f'\n  [OK]  {env_out.relative_to(ROOT)}')
    print(f'  [OK]  {compose_out.relative_to(ROOT)}')
    print(f"""
  Démarrer :
    composer install --no-dev --optimize-autoloader
    docker compose -f {compose_out.name} up -d

  Premier admin : admin@zencoparent.local / Admin1234!
  LICENSE_MASTER_KEY (à conserver précieusement) :
    {license_master}
""")


# -- Main ----------------------------------------------------------------------

def main() -> None:
    banner()

    mode = ask_choice(
        'Mode d\'installation',
        ['community', 'saas'],
        'community',
    )

    if mode == 'community':
        setup_community()
    else:
        setup_saas()

    print('  Configuration terminée.\n')


if __name__ == '__main__':
    try:
        main()
    except KeyboardInterrupt:
        print('\n\n  Annulé.\n')
        sys.exit(0)
