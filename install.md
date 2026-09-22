# ZenCoParent — Guide d'installation

ZenCoParent s'installe avec **Docker Compose**, en une seule édition. Toutes les fonctionnalités sont disponibles, sans restriction ni activation.

| | ZenCoParent |
|---|---|
| Hébergement | VPS ou cloud dédié |
| Base de données | PostgreSQL 16 (**obligatoire**) |
| Stockage fichiers | MinIO / S3 (optionnel) ou dossier local |
| Cache / rate-limit | Redis 7 (optionnel) |
| Multi-tenant | Oui |
| Difficulté | ⭐⭐ Intermédiaire |

---

## Prérequis

- Docker **24+** et Docker Compose **v2+**
- PHP **8.2+** et Composer **2.x** sur l'hôte (pour installer `vendor/`)
- 2 Go de RAM minimum (4 Go recommandés en production)
- Un nom de domaine avec DNS configuré (pour HTTPS)
- Git

```bash
docker --version
docker compose version
php -v
```

PostgreSQL, Redis et MinIO sont fournis par le `docker-compose.yml` — aucune installation manuelle n'est nécessaire.

---

## Étape 1 — Cloner le dépôt

```bash
git clone https://github.com/frenchtux/ZenCoParent.git
cd ZenCoParent
```

## Étape 2 — Installer les dépendances PHP

```bash
composer install --no-dev --optimize-autoloader
```

> Cette commande se lance **sur l'hôte** : le `docker-compose.yml` monte le dépôt entier (dont `vendor/`) dans les conteneurs PHP. Sans `vendor/` à jour, les conteneurs `migrate` et `seed` échouent au démarrage.

## Étape 3 — Configurer les variables d'environnement

```bash
cp .env.example .env.saas
```

> `.env.saas` est le fichier lu par défaut par le `docker-compose.yml`. Il est dans `.gitignore` — ne le commettez jamais.

Éditez `.env.saas` et renseignez **toutes** les valeurs marquées à changer :

```dotenv
# ─── Application ─────────────────────────────────────────────────────────────
APP_NAME=ZenCoParent
APP_ENV=production
APP_URL=https://votre-domaine.com
APP_DEBUG=false
APP_PORT=80                 # 8061 en développement local

# Générez avec : php -r "echo bin2hex(random_bytes(32));"
APP_SECRET=changez-moi-avec-une-chaine-aleatoire-de-64-caracteres

# ─── PostgreSQL (obligatoire) ────────────────────────────────────────────────
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=zencoparent
DB_USERNAME=zencoparent
DB_PASSWORD=changez-ce-mot-de-passe-fort

# ─── Redis (optionnel) ───────────────────────────────────────────────────────
# Laisser REDIS_HOST vide désactive le rate limiting ; l'application
# fonctionne normalement.
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null    # Mettez un mot de passe en production !
REDIS_DB=0

# ─── MinIO / S3 (optionnel) ──────────────────────────────────────────────────
# Laisser MINIO_ENDPOINT vide stocke les fichiers sur le disque local,
# aux emplacements STORAGE_PATH / STORAGE_URL ci-dessous.
MINIO_ENDPOINT=http://minio:9000
MINIO_ACCESS_KEY=changez-access-key
MINIO_SECRET_KEY=changez-secret-key
MINIO_BUCKET=zencoparent
MINIO_REGION=us-east-1

# ─── Stockage local (utilisé quand MINIO_ENDPOINT est vide) ──────────────────
STORAGE_PATH=/var/www/html/storage
STORAGE_URL=http://localhost/storage

# ─── JWT ─────────────────────────────────────────────────────────────────────
JWT_SECRET=changez-moi-autre-secret-long-et-aleatoire
JWT_EXPIRY=3600
JWT_REFRESH_EXPIRY=2592000

# ─── CSRF ────────────────────────────────────────────────────────────────────
CSRF_SECRET=changez-moi-csrf-secret

# ─── Rate limiting (ignoré sans Redis) ───────────────────────────────────────
RATE_LIMIT_REQUESTS=60
RATE_LIMIT_WINDOW=60

# ─── OAuth Google (optionnel) ────────────────────────────────────────────────
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=https://votre-domaine.com/auth/oauth/google/callback
```

> **Générer des secrets sécurisés :**
> ```bash
> php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
> # ou
> openssl rand -hex 32
> ```

> **Alternative interactive :** `python scripts/setup.py` est un wizard qui génère un fichier `.env.saas.generated` prérempli, à renommer en `.env.saas` après relecture.

## Étape 4 — Lancer les services

```bash
docker compose --env-file .env.saas up --build -d
```

Vérifiez que tous les conteneurs sont démarrés :

```bash
docker compose ps
```

Vous devez voir les services `nginx`, `php`, `postgres`, `redis` et `minio` avec le statut `running`, et les trois conteneurs d'init `migrate`, `seed` et `minio-init` en `exited (0)` — **c'est le comportement attendu** : ils s'exécutent une fois puis s'arrêtent.

Attendez que PostgreSQL soit prêt (le healthcheck le gère automatiquement, ~10 s) :

```bash
docker compose logs postgres --tail=10
```

## Étape 5 — Migrations, seed et bucket (automatiques)

Le `docker-compose.yml` exécute automatiquement, à chaque `up`, trois conteneurs d'init :

1. **`migrate`** — applique les migrations PostgreSQL en attente (`database/migrations/migrate.php`)
2. **`seed`** — crée le tenant `zencoparent` + l'admin par défaut (`seed_admin_saas.php`), idempotent
3. **`minio-init`** — crée le bucket `MINIO_BUCKET` s'il n'existe pas

Pour consulter leur sortie ou rejouer manuellement les migrations :

```bash
docker compose logs migrate seed minio-init
docker compose exec php php database/migrations/migrate.php
```

Le runner est **forward-only** : les migrations déjà appliquées (tracées dans la table `migrations`) sont ignorées, il n'y a pas de rollback.

## Étape 6 — Premier compte administrateur

L'admin par défaut est créé automatiquement par le conteneur `seed` :

| Champ | Valeur |
|---|---|
| Tenant (espace famille) | `zencoparent` |
| Email | `admin@zencoparent.local` |
| Mot de passe | `Admin1234!` |

> **Au premier login, un changement obligatoire d'email et de mot de passe est imposé** (modal bloquant). Définissez immédiatement des identifiants forts.

**Inscription publique** — `/frontend/register.html` (ou l'API ci-dessous) crée un **nouveau tenant familial** dont l'utilisateur est **parent** (jamais admin). Champs requis : `family_name`, `email`, `password`, `first_name`, `last_name`.

```bash
curl -s -X POST https://votre-domaine.com/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "family_name": "Famille Dupont",
    "email": "parent@exemple.com",
    "password": "MotDePasseFort!",
    "first_name": "Marie",
    "last_name": "Dupont"
  }' | python3 -m json.tool
```

Les comptes **admin** supplémentaires se créent depuis l'interface admin (`/frontend/utilisateurs.html`).

## Étape 7 — Stockage des fichiers

Deux options, pilotées par la seule variable `MINIO_ENDPOINT`.

**Option A — MinIO / S3 (`MINIO_ENDPOINT` renseignée)**

Le conteneur `minio-init` crée le bucket automatiquement. Pour vérifier ou ajuster la politique d'accès, ouvrez la console MinIO sur `http://votre-serveur:9001` :

1. Connectez-vous avec `MINIO_ACCESS_KEY` / `MINIO_SECRET_KEY`
2. Vérifiez la présence du bucket nommé selon `MINIO_BUCKET` (ex: `zencoparent`)
3. Politique d'accès du bucket : **Private**

Pour pointer vers AWS S3 plutôt que le MinIO local, renseignez `MINIO_ENDPOINT=https://s3.amazonaws.com`, la bonne `MINIO_REGION` et vos clés IAM.

**Option B — Disque local (`MINIO_ENDPOINT` vide)**

Les fichiers sont écrits dans `STORAGE_PATH` (par défaut `/var/www/html/storage` dans le conteneur, adossé au volume Docker `zencoparent_storage`) et exposés sous `STORAGE_URL`. L'application n'appelle plus MinIO du tout.

> Le service `php` déclare `depends_on: minio-init`, donc les conteneurs `minio` et `minio-init` continuent de démarrer même si l'application ne les utilise pas. Pour les supprimer complètement, il faut éditer le `docker-compose.yml` et retirer cette dépendance.

## Étape 8 — HTTPS en production

**Certbot (recommandé) :**

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d votre-domaine.com
```

**Traefik** (si plusieurs services sur le même hôte) : ajoutez les labels `traefik.enable=true` dans un `docker-compose.override.yml`.

---

## Vérification

```bash
# Tous les services longue durée sont "running"
docker compose ps

# La page de connexion répond (200)
curl -I http://localhost:8061/

# Le seed a bien créé le tenant et l'admin
docker compose exec postgres psql -U zencoparent -d zencoparent \
  -c "SELECT slug FROM tenants; SELECT email, role FROM users;"
```

Ouvrez ensuite `http://localhost:8061` (ou votre `APP_URL`) dans un navigateur — vous devez voir la page de connexion ZenCoParent.

---

## Tests

La suite PHPUnit tourne sur PostgreSQL, avec un schéma isolé par exécution : **Docker doit être démarré**.

```bash
docker exec zencoparent-php-1 sh -c 'cd /var/www/html && ./vendor/bin/phpunit'
```

---

## Mise à jour

```bash
# 1. Sauvegarder PostgreSQL
docker compose exec postgres pg_dump -U zencoparent zencoparent > backup_$(date +%Y%m%d_%H%M%S).sql

# 2. Mettre à jour le code et les dépendances
git pull origin main
composer install --no-dev --optimize-autoloader

# 3. Reconstruire et relancer (les migrations sont rejouées par le conteneur `migrate`)
docker compose --env-file .env.saas up --build -d --remove-orphans

# 4. Vérifier que les migrations sont passées
docker compose logs migrate --tail=30
```

---

## Dépannage

### Erreur 500 au premier accès

```bash
# Logs du conteneur PHP
docker compose logs php --tail=50

# Logs applicatifs (Monolog)
docker compose exec php tail -n 50 /var/www/html/storage/logs/app.log
```

### Les conteneurs `migrate` / `seed` échouent au démarrage

La cause la plus fréquente est un `vendor/` absent ou obsolète sur l'hôte :

```bash
composer install --no-dev --optimize-autoloader
docker compose --env-file .env.saas up -d
```

### Erreur de connexion PostgreSQL

Vérifiez que les variables correspondent bien à celles du `docker-compose.yml` :

```bash
docker compose exec php php -r "
  \$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s',
    getenv('DB_HOST'), getenv('DB_PORT'), getenv('DB_DATABASE')
  );
  \$pdo = new PDO(\$dsn, getenv('DB_USERNAME'), getenv('DB_PASSWORD'));
  echo 'PostgreSQL OK' . PHP_EOL;
"
```

> Les variables dans `docker-compose.yml` sont `${DB_DATABASE}` et `${DB_USERNAME}` — vérifiez qu'elles correspondent exactement à votre `.env.saas`.

### Redis non disponible (rate limiting)

Redis est **optionnel** : si `REDIS_HOST` est vide, l'application démarre sans rate limiting. Si vous l'avez configuré mais qu'il ne répond pas :

```bash
docker compose exec php php -r "
  \$r = new Redis();
  \$r->connect(getenv('REDIS_HOST'), (int)getenv('REDIS_PORT'));
  if (getenv('REDIS_PASSWORD') && getenv('REDIS_PASSWORD') !== 'null') {
    \$r->auth(getenv('REDIS_PASSWORD'));
  }
  echo \$r->ping() . PHP_EOL;
"
```

En dernier recours, videz `REDIS_HOST` dans `.env.saas` et relancez : l'application repassera sans rate limiting.

### Upload de fichiers en échec

MinIO est **optionnel**. Vérifiez d'abord que le bucket existe :

```bash
docker compose logs minio-init
```

Si vous n'utilisez pas MinIO, assurez-vous que `MINIO_ENDPOINT` est bien **vide** et que `STORAGE_PATH` est accessible en écriture :

```bash
docker compose exec php ls -la /var/www/html/storage
```

### JWT invalide / sessions expirées

Si toutes les sessions utilisateur sont invalidées après un redémarrage, vérifiez que `JWT_SECRET` n'a pas changé (il est rechargé depuis le fichier d'environnement à chaque boot). Tout changement de `JWT_SECRET` invalide tous les tokens actifs — c'est le comportement attendu.

### Permissions refusées sur `storage/`

L'entrypoint du conteneur PHP corrige les droits au démarrage. Pour les rétablir manuellement :

```bash
docker compose exec php chown -R www-data:www-data /var/www/html/storage
```

---

## Variables d'environnement — référence complète

| Variable | Requis | Valeur par défaut | Description |
|---|---|---|---|
| `APP_NAME` | Non | `ZenCoParent` | Nom affiché |
| `APP_ENV` | Oui | — | `production` ou `development` |
| `APP_URL` | Oui | — | URL publique de l'application |
| `APP_SECRET` | Oui | — | Secret applicatif (min. 32 chars) ; chiffre aussi les configs SMTP en base |
| `APP_DEBUG` | Non | `false` | Activer les traces d'erreurs |
| `APP_PORT` | Non | `80` | Port hôte exposé par nginx (ex: `8061` en dev) |
| `DB_CONNECTION` | Oui | `pgsql` | Toujours `pgsql` — PostgreSQL est le seul moteur supporté |
| `DB_HOST` | Oui | `postgres` | Hôte PostgreSQL |
| `DB_PORT` | Non | `5432` | Port PostgreSQL |
| `DB_DATABASE` | Oui | `zencoparent` | Nom de la base PostgreSQL |
| `DB_USERNAME` | Oui | — | Utilisateur PostgreSQL |
| `DB_PASSWORD` | Oui | — | Mot de passe PostgreSQL |
| `DB_SCHEMA` | Non | — | `search_path` PostgreSQL ; utilisé par la suite de tests pour isoler chaque exécution |
| `REDIS_HOST` | Non | `redis` | **Vide = pas de rate limiting** |
| `REDIS_PORT` | Non | `6379` | Port Redis |
| `REDIS_PASSWORD` | Non | `null` | La valeur littérale `null` désactive l'authentification |
| `REDIS_DB` | Non | `0` | Index de la base Redis |
| `MINIO_ENDPOINT` | Non | — | **Vide = stockage sur disque local** (ex: `http://minio:9000`) |
| `MINIO_PUBLIC_URL` | Non | = `MINIO_ENDPOINT` | URL publique servant les objets |
| `MINIO_ACCESS_KEY` | Si MinIO | — | Clé d'accès MinIO/S3 |
| `MINIO_SECRET_KEY` | Si MinIO | — | Clé secrète MinIO/S3 |
| `MINIO_BUCKET` | Si MinIO | `zencoparent` | Nom du bucket |
| `MINIO_REGION` | Non | `us-east-1` | Région MinIO/S3 |
| `STORAGE_PATH` | Si pas de MinIO | `storage/` du projet | Chemin du stockage local |
| `STORAGE_URL` | Si pas de MinIO | `/storage` | URL publique du stockage local |
| `JWT_SECRET` | Oui | — | Clé de signature JWT |
| `JWT_EXPIRY` | Non | `3600` | Durée du token en secondes |
| `JWT_REFRESH_EXPIRY` | Non | `2592000` | Durée du refresh token (30 j) |
| `CSRF_SECRET` | Oui | — | Clé CSRF double-submit |
| `RATE_LIMIT_REQUESTS` | Non | `60` | Requêtes max par fenêtre (ignoré sans Redis) |
| `RATE_LIMIT_WINDOW` | Non | `60` | Taille de la fenêtre en secondes (ignoré sans Redis) |
| `GOOGLE_CLIENT_ID` | Non | — | OAuth Google (optionnel) |
| `GOOGLE_CLIENT_SECRET` | Non | — | OAuth Google (optionnel) |
| `GOOGLE_REDIRECT_URI` | Non | — | Callback OAuth Google ; si vide, dérivé de `APP_URL` |
| `MAIL_HOST` | Non | — | Serveur SMTP global — vide désactive l'envoi d'e-mails. Surchargeable par tenant via l'admin |
| `MAIL_PORT` | Non | `587` | Port SMTP |
| `MAIL_ENCRYPTION` | Non | `tls` | `tls` ou `ssl` |
| `MAIL_USERNAME` | Non | — | Identifiant SMTP |
| `MAIL_PASSWORD` | Non | — | Mot de passe SMTP |
| `MAIL_FROM_ADDRESS` | Non | — | Adresse expéditeur |
| `MAIL_FROM_NAME` | Non | `ZenCoParent` | Nom expéditeur |

> **Configuration SMTP par tenant** : chaque administrateur peut définir son propre serveur SMTP depuis `/frontend/admin-parametres.html`. La config stockée en base (mot de passe chiffré AES-256) prime sur les variables `MAIL_*`. Un bouton « envoyer un test » permet de valider la configuration.

---

## Support

- Documentation : [github.com/frenchtux/ZenCoParent/wiki](https://github.com/frenchtux/ZenCoParent/wiki)
- Issues : [github.com/frenchtux/ZenCoParent/issues](https://github.com/frenchtux/ZenCoParent/issues)
- Licence : voir [LICENSE](LICENSE)
