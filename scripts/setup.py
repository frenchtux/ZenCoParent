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
+----------------------------------------------------------+
|          ZenCoParent -- Assistant de configuration        |
|          Generation du fichier .env                      |
+----------------------------------------------------------+
""")

# -- Configuration -------------------------------------------------------------

def setup() -> None:
    hr('Application')
    app_url  = ask("URL publique de l'application", 'http://localhost')
    app_port = ask_int('Port HTTP expose', 8061)

    hr('Base de donnees PostgreSQL')
    db_host = ask('DB_HOST',     'postgres')
    db_port = ask_int('DB_PORT', 5432)
    db_name = ask('DB_DATABASE', 'zencoparent')
    db_user = ask('DB_USERNAME', 'zencoparent')
    db_pass = ask('DB_PASSWORD', secret(32), required=True)

    hr('Redis -- optionnel (vide = pas de rate limiting)')
    redis_host = ask('REDIS_HOST', 'redis')
    redis_port = ask_int('REDIS_PORT', 6379) if redis_host else 6379
    redis_pass = ask('REDIS_PASSWORD', '') if redis_host else ''

    hr('MinIO -- optionnel (vide = fichiers sur disque local)')
    minio_endpoint = ask('MINIO_ENDPOINT (interne Docker)', 'http://minio:9000')
    if minio_endpoint:
        default_public = f'{app_url.rstrip("/")}:9000' if 'localhost' in app_url else app_url.rstrip('/') + '/minio'
        minio_public = ask('MINIO_PUBLIC_URL (accessible navigateur)', default_public)
        minio_bucket = ask('MINIO_BUCKET', 'zencoparent')
        minio_user   = ask('MINIO_ACCESS_KEY', 'minioadmin')
        minio_pass   = ask('MINIO_SECRET_KEY', secret(32), required=True)
        minio_region = ask('MINIO_REGION', 'us-east-1')
    else:
        minio_public = minio_bucket = minio_user = minio_pass = ''
        minio_region = 'us-east-1'

    hr('Email / SMTP (laisser vide pour desactiver)')
    mail_host = ask('MAIL_HOST', '')
    mail_port = ask_int('MAIL_PORT', 587) if mail_host else 587
    mail_enc  = ask_choice('MAIL_ENCRYPTION', ['tls', 'ssl', 'none'], 'tls') if mail_host else 'tls'
    mail_user = ask('MAIL_USERNAME', '') if mail_host else ''
    mail_pass = ask('MAIL_PASSWORD', '') if mail_host else ''
    mail_from = ask('MAIL_FROM_ADDRESS', 'noreply@zencoparent.com') if mail_host else 'noreply@zencoparent.com'
    mail_name = ask('MAIL_FROM_NAME', 'ZenCoParent') if mail_host else 'ZenCoParent'

    hr('Secrets (auto-generes -- modifiables)')
    app_secret  = ask('APP_SECRET',  secret(64))
    jwt_secret  = ask('JWT_SECRET',  secret(64))
    csrf_secret = ask('CSRF_SECRET', secret(48))

    env_content = f"""APP_NAME=ZenCoParent
APP_ENV=production
APP_SECRET={app_secret}
APP_URL={app_url}
APP_PORT={app_port}
APP_DEBUG=false

DB_CONNECTION=pgsql
DB_HOST={db_host}
DB_PORT={db_port}
DB_DATABASE={db_name}
DB_USERNAME={db_user}
DB_PASSWORD={db_pass}

# Vide = pas de rate limiting.
REDIS_HOST={redis_host}
REDIS_PORT={redis_port}
REDIS_PASSWORD={redis_pass}

JWT_SECRET={jwt_secret}
JWT_EXPIRY=3600
JWT_REFRESH_EXPIRY=2592000

CSRF_SECRET={csrf_secret}

# Utilise uniquement si MINIO_ENDPOINT est vide.
STORAGE_PATH=/var/www/html/storage
STORAGE_URL={app_url.rstrip('/')}/storage

# Vide = fichiers sur disque local.
MINIO_ENDPOINT={minio_endpoint}
MINIO_PUBLIC_URL={minio_public}
MINIO_ACCESS_KEY={minio_user}
MINIO_SECRET_KEY={minio_pass}
MINIO_BUCKET={minio_bucket}
MINIO_REGION={minio_region}

RATE_LIMIT_REQUESTS=60
RATE_LIMIT_WINDOW=60

MAIL_HOST={mail_host}
MAIL_PORT={mail_port}
MAIL_ENCRYPTION={mail_enc}
MAIL_USERNAME={mail_user}
MAIL_PASSWORD={mail_pass}
MAIL_FROM_ADDRESS={mail_from}
MAIL_FROM_NAME={mail_name}
"""

    out = ROOT / '.env.saas.generated'
    out.write_text(env_content, encoding='utf-8')

    print(f'\n  [OK]  {out.relative_to(ROOT)}')
    print(f"""
  Relisez le fichier, puis renommez-le :
    mv {out.name} .env.saas

  Demarrer :
    composer install --no-dev --optimize-autoloader
    docker compose --env-file .env.saas up --build -d

  Premier admin : admin@zencoparent.local / Admin1234!
  (changement des identifiants impose a la premiere connexion)
""")


# -- Main ----------------------------------------------------------------------

def main() -> None:
    banner()
    setup()
    print('  Configuration terminee.\n')


if __name__ == '__main__':
    try:
        main()
    except KeyboardInterrupt:
        print('\n\n  Annule.\n')
        sys.exit(0)
