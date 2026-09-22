# ZenCoParent

Application web de co-parentalité pour familles recomposées : calendrier partagé, messagerie, suivi des dépenses, dossiers médicaux enfants, galerie photos.

Une seule édition, installée via Docker Compose : PostgreSQL 16 obligatoire, Redis et MinIO optionnels. **Toutes les fonctionnalités sont disponibles, sans restriction.**

## Fonctionnalités

- **Authentification** JWT (cookie httpOnly) + CSRF double-submit, OAuth Google optionnel
- **Changement de credentials obligatoire** au premier login de l'admin
- **Multi-tenants** : un utilisateur peut accéder à plusieurs espaces familles et basculer de l'un à l'autre
- **Enfants & Calendrier** : événements avec date de début/fin, support multi-jours
- **Médical** : antécédents par enfant, **pièces jointes** (PDF/images), **compte-rendu de RDV obligatoire à la connexion suivante** du parent accompagnant
- **Messagerie** : conversations entre parents ou famille entière, avec **sujet**
- **Dépenses** partagées, **Photos** (galerie)
- **Invitations** : inviter un parent par email à rejoindre l'espace famille
- **RGPD** : export de ses données (JSON) + suppression de compte
- **Admin** : dashboard, gestion des familles et des utilisateurs, paramètres application / OAuth / sécurité, **config SMTP par tenant**

---

## Prérequis

| Outil | Version minimale | Note |
|---|---|---|
| Docker + Docker Compose | 24.x / v2 | Fournit PostgreSQL, Redis et MinIO |
| PHP | 8.2 | Sur l'hôte, pour lancer Composer |
| Composer | 2.x | Le dossier `vendor/` est monté dans les conteneurs |

> PostgreSQL 16, Redis 7 et MinIO sont démarrés par le `docker-compose.yml` — rien à installer manuellement.

---

## Installation

### 1. Cloner et installer les dépendances

```bash
git clone https://github.com/frenchtux/ZenCoParent.git
cd ZenCoParent
composer install --no-dev --optimize-autoloader
```

> `composer install` doit être joué **sur l'hôte** : le `docker-compose.yml` monte le dépôt (dont `vendor/`) dans les conteneurs PHP.

### 2. Configurer l'environnement

```bash
cp .env.example .env.saas   # Ne pas commettre ce fichier — il est dans .gitignore
```

Éditer `.env.saas` :

```env
APP_NAME=ZenCoParent
APP_ENV=production
APP_URL=https://votre-domaine.com
APP_DEBUG=false
APP_PORT=80                 # 8061 en développement local

# Générer avec : php -r "echo bin2hex(random_bytes(32));"
APP_SECRET=<chaîne aléatoire 64+ chars>

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=zencoparent
DB_USERNAME=zencoparent
DB_PASSWORD=<mot de passe fort>

# Redis — optionnel : laisser REDIS_HOST vide désactive le rate limiting
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DB=0

# MinIO — optionnel : laisser MINIO_ENDPOINT vide stocke les fichiers sur disque local
MINIO_ENDPOINT=http://minio:9000
MINIO_ACCESS_KEY=<clé>
MINIO_SECRET_KEY=<secret>
MINIO_BUCKET=zencoparent
MINIO_REGION=us-east-1

# Utilisés uniquement quand MINIO_ENDPOINT est vide
STORAGE_PATH=/var/www/html/storage
STORAGE_URL=http://localhost/storage

JWT_SECRET=<autre chaîne aléatoire>
JWT_EXPIRY=3600
JWT_REFRESH_EXPIRY=2592000

CSRF_SECRET=<autre chaîne aléatoire>
```

> Un wizard interactif est aussi disponible : `python scripts/setup.py` génère un fichier `.env.saas.generated` prérempli.

### 3. Démarrer les services

```bash
docker compose --env-file .env.saas up --build -d
```

Le compose démarre cinq services longue durée — `nginx`, `php`, `postgres`, `redis`, `minio` — et trois conteneurs d'init qui s'exécutent une fois puis **s'arrêtent normalement** :

1. **`migrate`** — applique les migrations PostgreSQL en attente (`database/migrations/migrate.php`)
2. **`seed`** — crée le tenant `zencoparent` + l'admin par défaut (`seed_admin_saas.php`), idempotent
3. **`minio-init`** — crée le bucket MinIO s'il n'existe pas

Aucune étape manuelle n'est nécessaire au premier démarrage. Vérifier l'état :

```bash
docker compose ps
```

Les conteneurs `migrate`, `seed` et `minio-init` en statut `exited (0)` sont le comportement attendu.

### 4. Premier accès

Ouvrir `http://localhost:8061` (ou l'`APP_URL` configurée) et se connecter avec le **compte admin par défaut** créé par le conteneur `seed` :

| Champ | Valeur |
|---|---|
| Tenant (espace famille) | `zencoparent` |
| Email | `admin@zencoparent.local` |
| Mot de passe | `Admin1234!` |

> **Au premier login, un changement obligatoire d'email et de mot de passe est imposé** (modal bloquant). Définissez vos propres identifiants à ce moment-là.

L'admin a accès au dashboard d'administration (`/frontend/admin.html`).

> **Inscription publique** : `/frontend/register.html` crée un nouveau tenant familial dont l'utilisateur est **parent** (jamais admin). Les comptes admin supplémentaires se créent depuis l'interface admin (`/frontend/utilisateurs.html`).

---

## Services optionnels

| Service | Variable déclencheuse | Comportement si vide |
|---|---|---|
| **Redis** | `REDIS_HOST` | Pas de rate limiting — l'application fonctionne normalement |
| **MinIO / S3** | `MINIO_ENDPOINT` | Les fichiers sont stockés sur le disque local (`STORAGE_PATH` / `STORAGE_URL`) |

Les deux services restent déclarés dans le `docker-compose.yml` ; vider la variable côté `.env.saas` suffit à ce que l'application ne s'en serve plus.

---

## Variables d'environnement — référence complète

| Variable | Obligatoire | Défaut | Description |
|---|---|---|---|
| `APP_NAME` | Non | `ZenCoParent` | Nom affiché |
| `APP_ENV` | Oui | — | `production` ou `development` |
| `APP_URL` | Oui | — | URL publique de l'instance |
| `APP_SECRET` | Oui | — | Secret applicatif (64+ chars) ; sert aussi au chiffrement AES-256 des configs SMTP en base |
| `APP_DEBUG` | Non | `false` | `true` en dev uniquement |
| `APP_PORT` | Non | `80` | Port hôte exposé par nginx (ex: `8061` en dev) |
| `DB_CONNECTION` | Oui | `pgsql` | Toujours `pgsql` — PostgreSQL est le seul moteur supporté |
| `DB_HOST` | Oui | `postgres` | Hôte PostgreSQL |
| `DB_PORT` | Non | `5432` | Port PostgreSQL |
| `DB_DATABASE` | Oui | `zencoparent` | Nom de la base |
| `DB_USERNAME` | Oui | — | Utilisateur PostgreSQL |
| `DB_PASSWORD` | Oui | — | Mot de passe PostgreSQL |
| `DB_SCHEMA` | Non | — | `search_path` PostgreSQL ; utilisé par la suite de tests pour isoler chaque exécution |
| `REDIS_HOST` | Non | `redis` | **Vide = pas de rate limiting** |
| `REDIS_PORT` | Non | `6379` | Port Redis |
| `REDIS_PASSWORD` | Non | `null` | La valeur littérale `null` désactive l'authentification |
| `REDIS_DB` | Non | `0` | Index de la base Redis |
| `MINIO_ENDPOINT` | Non | — | **Vide = stockage sur disque local** |
| `MINIO_PUBLIC_URL` | Non | = `MINIO_ENDPOINT` | URL publique servant les objets |
| `MINIO_ACCESS_KEY` | Si MinIO | — | Clé d'accès objet storage |
| `MINIO_SECRET_KEY` | Si MinIO | — | Secret objet storage |
| `MINIO_BUCKET` | Si MinIO | `zencoparent` | Nom du bucket |
| `MINIO_REGION` | Non | `us-east-1` | Région MinIO/S3 |
| `STORAGE_PATH` | Si pas de MinIO | `storage/` du projet | Chemin du stockage disque local |
| `STORAGE_URL` | Si pas de MinIO | `/storage` | URL publique du stockage local |
| `JWT_SECRET` | Oui | — | Secret de signature JWT |
| `JWT_EXPIRY` | Non | `3600` | Durée access token en secondes |
| `JWT_REFRESH_EXPIRY` | Non | `2592000` | Durée refresh token (30 j) |
| `CSRF_SECRET` | Oui | — | Secret CSRF double-submit |
| `RATE_LIMIT_REQUESTS` | Non | `60` | Requêtes max par fenêtre (ignoré sans Redis) |
| `RATE_LIMIT_WINDOW` | Non | `60` | Fenêtre de rate limiting en secondes (ignoré sans Redis) |
| `GOOGLE_CLIENT_ID` | Non | — | OAuth Google (optionnel) |
| `GOOGLE_CLIENT_SECRET` | Non | — | OAuth Google (optionnel) |
| `GOOGLE_REDIRECT_URI` | Non | — | Callback OAuth ; si vide, dérivé de `APP_URL` |
| `MAIL_HOST` | Non | — | Serveur SMTP (fallback global) — vide désactive l'envoi d'e-mails. Surchargeable **par tenant** via l'admin |
| `MAIL_PORT` | Non | `587` | Port SMTP |
| `MAIL_ENCRYPTION` | Non | `tls` | `tls` ou `ssl` |
| `MAIL_USERNAME` | Non | — | Identifiant SMTP |
| `MAIL_PASSWORD` | Non | — | Mot de passe SMTP |
| `MAIL_FROM_ADDRESS` | Non | — | Adresse expéditeur |
| `MAIL_FROM_NAME` | Non | `ZenCoParent` | Nom expéditeur |

> **SMTP par tenant** : chaque administrateur peut configurer son propre serveur SMTP depuis `/frontend/admin-parametres.html`. La config en base (chiffrée AES-256) prime sur les variables `MAIL_*` d'environnement.

---

## Migrations

Le runner `database/migrations/migrate.php` est **forward-only** (pas de rollback pour protéger l'intégrité). Il applique les fichiers `.sql` du dossier dans l'ordre alphabétique, sur PostgreSQL uniquement.

Les migrations sont jouées automatiquement à chaque `up` par le conteneur d'init `migrate`. Pour les rejouer manuellement :

```bash
docker compose exec php php database/migrations/migrate.php
```

Les migrations exécutées sont tracées dans la table `migrations` ; celles déjà appliquées sont ignorées.

---

## Structure du projet

```
├── database/migrations/    Migrations SQL PostgreSQL + runner PHP (migrate.php)
├── docker/                 Configs Nginx, PHP, Dockerfile
├── public/
│   ├── frontend/           Interface HTML/JS vanilla
│   └── index.php           Point d'entrée
├── scripts/                Wizard de configuration interactif (setup.py)
├── src/
│   ├── Api/                Controllers, Middleware, Routes
│   ├── Application/        Services applicatifs (CQRS handlers)
│   ├── Config/             Configuration (app, auth, database, redis)
│   ├── Domain/             Entités, Interfaces repositories
│   ├── Infrastructure/     Persistence PostgreSQL, Storage, Cache, Auth, Notification
│   └── bootstrap/          DI container, chargement de l'app
├── tests/                  PHPUnit — Unit, Integration, E2E
├── .env.example            Template de configuration
└── docker-compose.yml
```

---

## Développement local

```bash
# APP_PORT=8061 dans .env.saas → http://localhost:8061
docker compose --env-file .env.saas up --build -d

# Suivre les logs applicatifs
docker compose logs -f php
```

Les tests PHPUnit tournent sur PostgreSQL (un schéma isolé par exécution) : **Docker doit être démarré**.

```bash
docker exec zencoparent-php-1 sh -c 'cd /var/www/html && ./vendor/bin/phpunit'
```

---

## Licence

**GNU General Public License v3.0 ou ultérieure** — voir [LICENSE](LICENSE).

Vous êtes libre d'utiliser, modifier et redistribuer ce logiciel. Toute
redistribution, modifiée ou non, doit rester sous la même licence et donner
accès au code source.
