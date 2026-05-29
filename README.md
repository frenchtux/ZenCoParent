# ZenCoParent

Application web de co-parentalité pour familles recomposées : calendrier partagé, messagerie, suivi des dépenses, dossiers médicaux enfants, galerie photos.

Disponible en deux modes :
- **Community** — auto-hébergé, SQLite, aucun abonnement, une famille par instance
- **SaaS** — multi-tenant PostgreSQL + Stripe, toutes les familles sur une instance partagée

## Fonctionnalités

- **Authentification** JWT (cookie httpOnly) + CSRF double-submit, OAuth Google optionnel
- **Changement de credentials obligatoire** au premier login de l'admin
- **Multi-tenants** : un utilisateur peut accéder à plusieurs espaces familles et basculer de l'un à l'autre
- **Enfants & Calendrier** : événements avec date de début/fin, support multi-jours
- **Médical** : antécédents par enfant, **pièces jointes** (PDF/images), **compte-rendu de RDV obligatoire à la connexion suivante** du parent accompagnant
- **Messagerie** : conversations entre parents ou famille entière, avec **sujet**
- **Dépenses** partagées, **Photos** (galerie)
- **RGPD** : export de ses données (JSON) + suppression de compte
- **Admin** : dashboard, gestion des familles/modules, plans, paiements, **config SMTP par tenant**
- **Monétisation SaaS** : licence à paiement unique (150 €) + abonnements mensuels par famille (Stripe)

---

## Prérequis

| Outil | Version minimale |
|---|---|
| PHP | 8.2 |
| Composer | 2.x |
| Docker + Docker Compose | 24.x |
| (SaaS) PostgreSQL | 16 |
| (SaaS) Redis | 7 |
| (SaaS) MinIO ou AWS S3 | — |

---

## Mode Community (auto-hébergé, SQLite)

### 1. Cloner et installer les dépendances

```bash
git clone https://github.com/frenchtux/ZenCoParent.git
cd ZenCoParent
composer install --no-dev --optimize-autoloader
```

### 2. Configurer l'environnement

```bash
cp .env.example .env
```

Éditer `.env` avec au minimum :

```env
APP_MODE=community
APP_SECRET=<chaîne aléatoire longue>
JWT_SECRET=<autre chaîne aléatoire>
CSRF_SECRET=<autre chaîne aléatoire>
APP_URL=http://localhost:8080
```

### 3. Initialiser la base et le compte admin

```bash
php database/migrations/migrate_sqlite.php   # applique les 23 migrations sur SQLite
php seed_admin.php                           # crée le tenant + l'admin par défaut
```

La base SQLite est créée dans `storage/database.sqlite`.

### 4. Lancer le serveur de développement

```bash
php -S localhost:8080 -t public/ public/router.php
```

> Le script `public/router.php` est **indispensable** avec le serveur intégré PHP :
> il sert les fichiers statiques du frontend et route le reste vers `index.php`.
> Sans lui, `/frontend/*` renvoie l'API au lieu des pages.

### 5. Via Docker (recommandé)

```bash
docker compose -f docker-compose.community.yml up --build
```

L'entrypoint applique automatiquement les migrations et crée l'admin par défaut.

### 6. Premier accès

Ouvrir `http://localhost:8080` et se connecter avec le **compte admin par défaut** :

| Champ | Valeur |
|---|---|
| Tenant (espace famille) | `zencoparent` |
| Email | `admin@zencoparent.local` |
| Mot de passe | `Admin1234!` |

> **Au premier login, un changement obligatoire d'email et de mot de passe est imposé** (modal bloquant). Définissez vos propres identifiants à ce moment-là.

---

## Mode SaaS (multi-tenant, PostgreSQL)

### 1. Cloner et installer les dépendances

```bash
git clone https://github.com/frenchtux/ZenCoParent.git
cd ZenCoParent
composer install --no-dev --optimize-autoloader
```

### 2. Configurer l'environnement

```bash
cp .env.example .env.saas   # Ne pas commettre ce fichier — il est dans .gitignore
```

Éditer `.env.saas` :

```env
APP_MODE=saas
APP_SECRET=<chaîne aléatoire 64+ chars>
APP_URL=https://votre-domaine.com

DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=zencoparent
DB_USERNAME=zencoparent
DB_PASSWORD=<mot de passe fort>

REDIS_HOST=redis
REDIS_PORT=6379

MINIO_ENDPOINT=http://minio:9000   # ou https://s3.amazonaws.com pour AWS
MINIO_ACCESS_KEY=<clé>
MINIO_SECRET_KEY=<secret>
MINIO_BUCKET=zencoparent
MINIO_REGION=eu-west-3

JWT_SECRET=<chaîne aléatoire>
CSRF_SECRET=<chaîne aléatoire>

# Clé maître de licence — ne jamais commettre, ne jamais partager
LICENSE_MASTER_KEY=<chaîne aléatoire longue et unique>

# Stripe (créer les clés sur https://dashboard.stripe.com/apikeys)
STRIPE_SECRET_KEY=sk_live_...
STRIPE_PUBLISHABLE_KEY=pk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_INSTALLATION_KEY_PRICE_ID=price_...
```

### 3. Démarrer les services

```bash
ZENCO_ENV_FILE=.env.saas docker compose up --build -d
```

Le `docker-compose.yml` inclut deux conteneurs d'init qui s'exécutent automatiquement dans l'ordre :
1. **`migrate`** — applique les 23 migrations PostgreSQL (`migrate.php`)
2. **`seed`** — crée le tenant `zencoparent` + l'admin par défaut (`seed_admin_saas.php`)

Aucune étape manuelle n'est nécessaire au premier démarrage. Pour rejouer manuellement :

```bash
docker compose exec php php database/migrations/migrate.php
```

Les 23 migrations sont appliquées dans l'ordre ; celles déjà exécutées (table `migrations`) sont ignorées.

### 5. Configurer le webhook Stripe

Dans le Dashboard Stripe → Webhooks → Ajouter un endpoint :

```
URL        : https://votre-domaine.com/payments/webhook
Événements : checkout.session.completed
             customer.subscription.updated
             customer.subscription.deleted
             invoice.payment_succeeded
             invoice.payment_failed
```

Copier le `Signing secret` dans `STRIPE_WEBHOOK_SECRET`.

### 6. Configurer les plans dans Stripe

Pour chaque plan (Family, Premium) :
1. Créer un produit dans Stripe Dashboard
2. Créer deux prix (mensuel + annuel)
3. Copier les `price_id` dans la table `plans` via :

```sql
UPDATE plans
SET stripe_price_id_monthly = 'price_xxx',
    stripe_price_id_yearly  = 'price_yyy'
WHERE name = 'family';
```

### 7. Premier accès

Se connecter avec l'**admin par défaut** créé par le conteneur `seed` :

| Champ | Valeur |
|---|---|
| Tenant | `zencoparent` |
| Email | `admin@zencoparent.local` |
| Mot de passe | `Admin1234!` |

Un **changement obligatoire d'email + mot de passe** est imposé au premier login. L'admin a accès au dashboard d'administration (`/frontend/admin.html`).

> **Inscription publique** : `/frontend/register.html` crée un nouveau tenant familial dont l'utilisateur est **parent** (jamais admin). Les comptes admin supplémentaires se créent depuis l'interface admin (`/frontend/utilisateurs.html`).

---

## Variables d'environnement — référence complète

| Variable | Obligatoire | Description |
|---|---|---|
| `APP_MODE` | Oui | `community` ou `saas` |
| `APP_SECRET` | Oui | Secret HMAC général (64+ chars) |
| `APP_URL` | Oui | URL publique de l'instance |
| `APP_DEBUG` | Non | `true` en dev uniquement |
| `DB_HOST` | SaaS | Hôte PostgreSQL |
| `DB_DATABASE` | SaaS | Nom de la base |
| `DB_USERNAME` | SaaS | Utilisateur PostgreSQL |
| `DB_PASSWORD` | SaaS | Mot de passe PostgreSQL |
| `DB_FILE` | Community | Chemin SQLite (défaut: `storage/database.sqlite`) |
| `REDIS_HOST` | SaaS | Hôte Redis |
| `MINIO_ENDPOINT` | SaaS | URL MinIO ou S3 |
| `MINIO_ACCESS_KEY` | SaaS | Clé d'accès objet storage |
| `MINIO_SECRET_KEY` | SaaS | Secret objet storage |
| `MINIO_BUCKET` | SaaS | Nom du bucket |
| `JWT_SECRET` | Oui | Secret de signature JWT |
| `JWT_EXPIRY` | Non | Durée access token en secondes (défaut: 3600) |
| `JWT_REFRESH_EXPIRY` | Non | Durée refresh token (défaut: 2592000 = 30j) |
| `CSRF_SECRET` | Oui | Secret CSRF |
| `LICENSE_MASTER_KEY` | SaaS | Clé maître de dérivation des licences — ne pas commettre |
| `STRIPE_SECRET_KEY` | SaaS | Clé secrète Stripe (`sk_live_...`) |
| `STRIPE_PUBLISHABLE_KEY` | SaaS | Clé publique Stripe (`pk_live_...`) |
| `STRIPE_WEBHOOK_SECRET` | SaaS | Secret de vérification webhook Stripe |
| `STRIPE_INSTALLATION_KEY_PRICE_ID` | SaaS | Price ID pour l'achat d'une clé d'installation |
| `GOOGLE_CLIENT_ID` | Non | OAuth Google (optionnel) |
| `GOOGLE_CLIENT_SECRET` | Non | OAuth Google (optionnel) |
| `RATE_LIMIT_REQUESTS` | Non | Requêtes max par fenêtre (défaut: 60) |
| `RATE_LIMIT_WINDOW` | Non | Fenêtre de rate limiting en secondes (défaut: 60) |
| `MAIL_HOST` | Non | Serveur SMTP (fallback global). Surchargeable **par tenant** via l'admin |
| `MAIL_PORT` | Non | Port SMTP (défaut: 587) |
| `MAIL_ENCRYPTION` | Non | `tls` ou `ssl` |
| `MAIL_USERNAME` | Non | Identifiant SMTP |
| `MAIL_PASSWORD` | Non | Mot de passe SMTP |
| `MAIL_FROM_ADDRESS` | Non | Adresse expéditeur |
| `MAIL_FROM_NAME` | Non | Nom expéditeur |
| `APP_PORT` | Non | Port hôte exposé par nginx (défaut: 80 ; ex: 8061 en dev) |

> **SMTP par tenant** : chaque administrateur peut configurer son propre serveur SMTP depuis `/frontend/admin-parametres.html`. La config en base (chiffrée AES-256) prime sur les variables `MAIL_*` d'environnement.

---

## Migrations

Le runner `database/migrations/migrate.php` est **forward-only** (pas de rollback pour protéger l'intégrité).

```bash
# Appliquer les migrations en attente
php database/migrations/migrate.php

# En mode SaaS depuis Docker
docker compose exec php php database/migrations/migrate.php
```

Les migrations exécutées sont tracées dans la table `migrations`.

---

## Structure du projet

```
├── database/migrations/    Scripts SQL (001–023) + runner PHP
│   └── sqlite/             Overrides SQLite des migrations PostgreSQL-spécifiques
├── docker/                 Configs Nginx, PHP, Dockerfile
├── public/
│   ├── frontend/           Interface HTML/JS vanilla
│   └── index.php           Point d'entrée
├── src/
│   ├── Api/                Controllers, Middleware, Routes
│   ├── Application/        Services applicatifs (CQRS handlers)
│   ├── Domain/             Entités, Interfaces repositories
│   ├── Infrastructure/     Persistence (SQLite + PostgreSQL), Stripe, Auth
│   └── bootstrap/          DI container, chargement de l'app
├── .env.example            Template de configuration
└── docker-compose.yml
```

---

## Modules et plans (SaaS)

| Module | Plan Free | Plan Family | Plan Premium |
|---|---|---|---|
| Enfants + Calendrier | Inclus | Inclus | Inclus |
| Dépenses | — | Inclus | Inclus |
| Photos | — | Inclus | Inclus |
| Messagerie | — | Inclus | Inclus |
| Dossiers médicaux | — | Inclus | Inclus |

Les modules peuvent être activés/désactivés individuellement par famille depuis le dashboard admin (`/frontend/admin.html`).

---

## Développement local

```bash
# Mode community — SQLite, pas de Docker (router.php obligatoire)
php -S localhost:8080 -t public/ public/router.php

# Mode SaaS — nécessite PostgreSQL + Redis + MinIO (Docker Compose)
ZENCO_ENV_FILE=.env.saas docker compose up
```

Les tests PHPUnit :

```bash
composer test
```

---

## Licence

Propriétaire — voir `composer.json`.
