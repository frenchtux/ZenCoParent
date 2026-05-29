# ZenCoParent — Guide d'installation

Choisissez votre mode d'installation selon votre contexte :

| | Community | SaaS |
|---|---|---|
| Hébergement | Mutualisé / VPS simple | VPS ou cloud dédié |
| Base de données | SQLite (incluse) | PostgreSQL 16 |
| Stockage fichiers | Dossier local | MinIO / S3 |
| Cache / rate-limit | Désactivé (fichier) | Redis 7 |
| Multi-tenant | Non (famille unique) | Oui |
| Photos / galerie | Non | Oui |
| Licence requise | Non | Oui (30 j d'essai gratuit) |
| Difficulté | ⭐ Débutant | ⭐⭐ Intermédiaire |

---

## Mode 1 — Community (ZIP + PHP)

Installation en moins de 5 minutes sur n'importe quel hébergement avec PHP 8.2+.

### Prérequis

- PHP **8.2** ou supérieur
- Extensions PHP : `pdo`, `pdo_sqlite`, `mbstring`, `json`, `openssl`, `fileinfo`
- Serveur web : Apache 2.4+ (avec `mod_rewrite`) ou Nginx 1.20+
- Accès en ligne de commande (SSH ou terminal local)
- Composer 2.x

Vérifiez votre version de PHP et les extensions :

```bash
php -v
php -m | grep -E 'pdo|pdo_sqlite|mbstring|json|openssl|fileinfo'
```

### Étape 1 — Télécharger et déposer les fichiers

```bash
# Remplacez x.y.z par le numéro de version
wget https://github.com/frenchtux/ZenCoParent/releases/download/vx.y.z/zencoparent-community-x.y.z.zip
unzip zencoparent-community-x.y.z.zip -d zencoparent

# Déposer dans la racine web
sudo mv zencoparent /var/www/html/zencoparent   # Apache
# ou
sudo mv zencoparent /var/www/zencoparent        # Nginx
```

### Étape 2 — Installer les dépendances PHP

```bash
cd /var/www/html/zencoparent
composer install --no-dev --optimize-autoloader
```

### Étape 3 — Lancer le wizard d'installation

Le script `install.php` configure tout de façon interactive :

```bash
php install.php
```

Il vous demande :

1. **Chemin SQLite** — emplacement de la base (ex: `/var/www/html/zencoparent/database/zencoparent.sqlite`)
2. **Chemin de stockage** — dossier des fichiers (ex: `/var/www/html/zencoparent/storage`)
3. **URL de l'application** — URL publique (ex: `https://monsite.com`)
4. **Email admin** — votre adresse email
5. **Mot de passe admin** — 8 caractères minimum
6. **Prénom / Nom** de l'administrateur
7. **Nom de famille** — le « tenant » (une seule famille en mode Community)

À la fin, il :
- Crée automatiquement le fichier `.env`
- Applique les 13 migrations SQL sur la base SQLite
- Crée le tenant et le compte administrateur
- Affiche un bloc de configuration Nginx prêt à l'emploi

> **Supprimez `install.php` après l'installation** — il n'est plus nécessaire et expose les chemins système.

```bash
rm install.php
```

### Étape 4 — Permissions des dossiers

```bash
# Debian/Ubuntu
sudo chown -R www-data:www-data database/ storage/ logs/
sudo chmod -R 755 database/ storage/ logs/

# CentOS/RHEL/AlmaLinux
sudo chown -R apache:apache database/ storage/ logs/
sudo chmod -R 755 database/ storage/ logs/
```

### Étape 5 — Configurer le serveur web

**Apache** — créez `/etc/apache2/sites-available/zencoparent.conf` :

```apacheconf
<VirtualHost *:80>
    ServerName monsite.com
    DocumentRoot /var/www/html/zencoparent/public

    <Directory /var/www/html/zencoparent/public>
        Options -Indexes
        AllowOverride All
        Require all granted
    </Directory>

    # Bloquer l'accès aux répertoires sensibles
    <Directory /var/www/html/zencoparent/database>
        Require all denied
    </Directory>
    <Directory /var/www/html/zencoparent/storage>
        Require all granted
    </Directory>
</VirtualHost>
```

```bash
sudo a2enmod rewrite
sudo a2ensite zencoparent
sudo systemctl reload apache2
```

**Nginx** — copiez le bloc affiché par `install.php` dans `/etc/nginx/sites-available/zencoparent.conf`, puis :

```bash
sudo ln -s /etc/nginx/sites-available/zencoparent.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Le bloc Nginx généré ressemble à ceci (adaptez les chemins) :

```nginx
server {
    listen 80;
    server_name monsite.com;
    root /var/www/html/zencoparent/public;
    index index.php;

    # Fichiers statiques (stockage local)
    location /storage/ {
        alias /var/www/html/zencoparent/storage/;
        expires 7d;
    }

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Bloquer les fichiers sensibles
    location ~* \.(env|sqlite|log|sh|sql)$ { deny all; return 404; }
    location ~ /\.(env|git) { deny all; }
}
```

### Vérification

Ouvrez `http://monsite.com` dans votre navigateur — vous devez voir la page de connexion ZenCoParent.

Connectez-vous avec le compte administrateur créé à l'étape 3.

> **HTTPS (recommandé) :** `sudo certbot --nginx -d monsite.com`

---

## Mode 2 — SaaS (Docker Compose)

Installation complète avec PostgreSQL multi-tenant, Redis, MinIO et gestion des licences.

### Prérequis

- Docker **24+** et Docker Compose **v2+**
- 2 Go de RAM minimum (4 Go recommandés en production)
- Un nom de domaine avec DNS configuré (pour HTTPS)
- Git

```bash
docker --version
docker compose version
```

### Étape 1 — Cloner le dépôt

```bash
git clone https://github.com/frenchtux/ZenCoParent.git
cd ZenCoParent
```

### Étape 2 — Configurer les variables d'environnement

```bash
cp .env.example .env
```

Éditez `.env` et renseignez **toutes** les valeurs marquées à changer :

```dotenv
# ─── Mode application ────────────────────────────────────────────────────────
APP_NAME=ZenCoParent
APP_ENV=production
APP_MODE=saas
APP_URL=https://votre-domaine.com
APP_DEBUG=false

# Générez avec : php -r "echo bin2hex(random_bytes(32));"
APP_SECRET=changez-moi-avec-une-chaine-aleatoire-de-64-caracteres

# ─── PostgreSQL ──────────────────────────────────────────────────────────────
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=zencoparent
DB_USERNAME=zencoparent
DB_PASSWORD=changez-ce-mot-de-passe-fort

# ─── Redis ───────────────────────────────────────────────────────────────────
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null    # Mettez un mot de passe en production !
REDIS_DB=0

# ─── MinIO (stockage objet S3-compatible) ────────────────────────────────────
MINIO_ENDPOINT=http://minio:9000
MINIO_ACCESS_KEY=changez-access-key
MINIO_SECRET_KEY=changez-secret-key
MINIO_BUCKET=zencoparent
MINIO_REGION=us-east-1

# ─── JWT ─────────────────────────────────────────────────────────────────────
JWT_SECRET=changez-moi-autre-secret-long-et-aleatoire
JWT_EXPIRY=3600
JWT_REFRESH_EXPIRY=2592000

# ─── CSRF ────────────────────────────────────────────────────────────────────
CSRF_SECRET=changez-moi-csrf-secret

# ─── Licence SaaS ────────────────────────────────────────────────────────────
# Clé maître connue UNIQUEMENT de l'opérateur/éditeur.
# Permet de dériver les clés d'activation pour chaque installation.
# Voir section "Clé d'activation" ci-dessous.
LICENSE_MASTER_KEY=changez-cette-cle-avant-toute-distribution

# ─── Rate limiting ───────────────────────────────────────────────────────────
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

### Étape 3 — Lancer les services

```bash
docker compose up -d
```

Vérifiez que tous les conteneurs sont démarrés :

```bash
docker compose ps
```

Vous devez voir les services `nginx`, `php`, `postgres`, `redis` et `minio` avec le statut `running`.

Attendez que PostgreSQL soit prêt (le healthcheck le gère automatiquement, ~10 s) :

```bash
docker compose logs postgres --tail=10
```

### Étape 4 — Appliquer les migrations

```bash
docker compose exec php php database/migrations/migrate.php
```

Les 13 migrations sont appliquées dans l'ordre, y compris la table `app_license` (migration 013).

### Étape 5 — Créer le premier compte administrateur

Le premier administrateur s'inscrit via l'API — cela crée simultanément le premier tenant :

```bash
curl -s -X POST https://votre-domaine.com/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@votre-domaine.com",
    "password": "MotDePasseAdmin!",
    "first_name": "Admin",
    "last_name": "Principal",
    "tenant_name": "Mon Organisation",
    "role": "admin"
  }' | python3 -m json.tool
```

### Étape 6 — Configurer MinIO

Accédez à la console MinIO sur `http://votre-serveur:9001` :

1. Connectez-vous avec `MINIO_ACCESS_KEY` / `MINIO_SECRET_KEY`
2. Créez un bucket nommé selon la valeur de `MINIO_BUCKET` (ex: `zencoparent`)
3. Politique d'accès du bucket : **Private**

Ou via la CLI depuis le conteneur :

```bash
docker compose exec minio sh -c "
  mc alias set local http://localhost:9000 \$MINIO_ROOT_USER \$MINIO_ROOT_PASSWORD &&
  mc mb local/zencoparent &&
  mc policy set private local/zencoparent
"
```

### Étape 7 — HTTPS en production

**Certbot (recommandé) :**

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d votre-domaine.com
```

**Traefik** (si plusieurs services sur le même hôte) : ajoutez les labels `traefik.enable=true` dans un `docker-compose.override.yml`.

### Vérification

```bash
# Santé de l'API
curl -s https://votre-domaine.com/health

# Statut de la licence (retourne la clé d'installation)
curl -s https://votre-domaine.com/license | python3 -m json.tool
```

---

## Clé d'activation SaaS

Le mode SaaS inclut une gestion de licence. Voici comment elle fonctionne de bout en bout.

### Fonctionnement

```
INSTALLATION                    OPÉRATEUR                      CLIENT
─────────────────────────────────────────────────────────────────────
1. Premier démarrage        →   Clé d'installation auto-générée
   (stockée en base)            ZNCO-AB12-CD34-EF56-78AB-CDEF

2. Le client appelle            ← GET /license
   et récupère sa clé

3. Le client envoie sa      →   php generate-activation-key.php ZNCO-AB12-...
   clé d'installation           ↓ produit
                                ACT-F3A2-91BC-4D7E-0821-A5CF

4. L'opérateur renvoie la   →   POST /license/activate
   clé d'activation             {"activation_key": "ACT-F3A2-..."}

                                ← Licence activée ✓
```

### Période d'essai

Après l'installation, l'application est utilisable **sans clé d'activation pendant 30 jours**. Passé ce délai, toute requête API retourne :

```json
{
  "success": false,
  "error": "license_expired",
  "message": "La période d'essai de 30 jours a expiré.",
  "data": {
    "installation_key": "ZNCO-AB12-CD34-EF56-78AB-CDEF",
    "trial_days_remaining": 0,
    "is_active": false
  }
}
```

### Étape 1 — Récupérer la clé d'installation (côté client)

Le client appelle l'endpoint public (sans authentification) :

```bash
curl -s https://votre-domaine.com/license
```

Réponse :

```json
{
  "success": true,
  "data": {
    "installation_key": "ZNCO-AB12-CD34-EF56-78AB-CDEF",
    "is_trial_active": true,
    "trial_days_remaining": 27,
    "is_active": false,
    "activated_at": null,
    "installed_at": "2026-05-28T14:32:00+00:00",
    "is_licensed": true
  }
}
```

Le client vous communique son `installation_key` (ex: par email ou portail client).

### Étape 2 — Générer la clé d'activation (côté opérateur)

La clé d'activation est dérivée de la clé d'installation par HMAC-SHA256, en utilisant votre `LICENSE_MASTER_KEY`. **Cette opération se fait côté opérateur uniquement — ne partagez jamais `LICENSE_MASTER_KEY`.**

**Option A — Script dédié** (recommandé, à placer dans `bin/generate-activation-key.php`) :

```php
<?php
// bin/generate-activation-key.php
// Usage : php bin/generate-activation-key.php ZNCO-AB12-CD34-EF56-78AB-CDEF

require __DIR__ . '/../vendor/autoload.php';
(Dotenv\Dotenv::createImmutable(dirname(__DIR__)))->load();

$installationKey = trim($argv[1] ?? '');
if (!$installationKey) {
    fwrite(STDERR, "Usage: php bin/generate-activation-key.php <INSTALLATION_KEY>\n");
    exit(1);
}

$masterKey = $_ENV['LICENSE_MASTER_KEY'] ?? '';
if (!$masterKey || str_contains($masterKey, 'changez')) {
    fwrite(STDERR, "Erreur : LICENSE_MASTER_KEY non configurée dans .env\n");
    exit(1);
}

$hmac  = strtoupper(hash_hmac('sha256', $installationKey, $masterKey));
$chars = substr($hmac, 0, 20);
$key   = 'ACT-' . implode('-', str_split($chars, 4));

echo "Installation key : {$installationKey}\n";
echo "Activation key   : {$key}\n";
```

```bash
# Utilisation
php bin/generate-activation-key.php ZNCO-AB12-CD34-EF56-78AB-CDEF

# Sortie
Installation key : ZNCO-AB12-CD34-EF56-78AB-CDEF
Activation key   : ACT-F3A2-91BC-4D7E-0821-A5CF
```

**Option B — One-liner PHP** (si vous préférez sans script) :

```bash
php -r "
\$install = 'ZNCO-AB12-CD34-EF56-78AB-CDEF';   // ← clé du client
\$master  = 'votre-license-master-key';          // ← votre LICENSE_MASTER_KEY
\$hmac    = strtoupper(hash_hmac('sha256', \$install, \$master));
\$chars   = substr(\$hmac, 0, 20);
echo 'ACT-' . implode('-', str_split(\$chars, 4)) . PHP_EOL;
"
```

> **Important :** La même `LICENSE_MASTER_KEY` doit être utilisée pour tous vos clients. Si vous la changez, les clés d'activation déjà émises deviennent invalides. Stockez-la dans un gestionnaire de secrets (ex: Vault, AWS Secrets Manager).

### Étape 3 — Activer la licence (côté client)

Le client soumet la clé d'activation via l'API :

```bash
curl -s -X POST https://votre-domaine.com/license/activate \
  -H "Content-Type: application/json" \
  -d '{"activation_key": "ACT-F3A2-91BC-4D7E-0821-A5CF"}' \
  | python3 -m json.tool
```

Réponse en cas de succès :

```json
{
  "success": true,
  "data": {
    "installation_key": "ZNCO-AB12-CD34-EF56-78AB-CDEF",
    "is_trial_active": true,
    "trial_days_remaining": 27,
    "is_active": true,
    "activated_at": "2026-05-28T16:00:00+00:00",
    "installed_at": "2026-05-28T14:32:00+00:00",
    "is_licensed": true
  }
}
```

Réponse en cas de clé invalide :

```json
{
  "success": false,
  "error": "Clé d'activation invalide.",
  "code": 422
}
```

### Résumé des endpoints licence

| Méthode | Endpoint | Auth | Description |
|---|---|---|---|
| `GET` | `/license` | Non | Statut de la licence + clé d'installation |
| `POST` | `/license/activate` | Non | Soumettre une clé d'activation |

---

## Mise à jour

### Community

```bash
# 1. Sauvegarder la base et la config
cp database/zencoparent.sqlite database/zencoparent.sqlite.bak
cp .env .env.bak

# 2. Télécharger et décompresser la nouvelle version
# (les fichiers applicatifs sont remplacés, storage/ et database/ sont préservés)
wget https://github.com/frenchtux/ZenCoParent/releases/download/vX.Y.Z/zencoparent-community-X.Y.Z.zip
unzip -o zencoparent-community-X.Y.Z.zip -x "storage/*" "database/*.sqlite" ".env"

# 3. Mettre à jour les dépendances
composer install --no-dev --optimize-autoloader

# 4. Appliquer les nouvelles migrations
php database/migrations/migrate_sqlite.php

# 5. Réinitialiser les permissions
sudo chown -R www-data:www-data database/ storage/ logs/
```

### SaaS

```bash
# 1. Sauvegarder PostgreSQL
docker compose exec postgres pg_dump -U zencoparent zencoparent > backup_$(date +%Y%m%d_%H%M%S).sql

# 2. Mettre à jour le code et les images
git pull origin main
docker compose pull
docker compose up -d --remove-orphans

# 3. Appliquer les nouvelles migrations
docker compose exec php php database/migrations/migrate.php
```

---

## Dépannage

### Erreur 500 au premier accès

```bash
# Community — lire les logs PHP-FPM
sudo tail -f /var/log/php8.2-fpm.log

# Vérifier les permissions de storage/
ls -la storage/ database/

# SaaS — logs du conteneur PHP
docker compose logs php --tail=50
```

### Erreur de connexion PostgreSQL (SaaS)

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

> Les variables dans `docker-compose.yml` sont `${DB_DATABASE}` et `${DB_USERNAME}` — vérifiez qu'elles correspondent exactement à votre `.env`.

### Redis non disponible (rate limiting)

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

### Licence expirée — accès impossible (SaaS)

Si l'essai de 30 jours est expiré et que vous n'avez pas encore de clé d'activation :

```bash
# Vérifier le statut actuel
curl -s http://localhost/license | python3 -m json.tool
```

Récupérez la valeur `installation_key` et suivez la procédure de la section [**Clé d'activation SaaS**](#clé-dactivation-saas).

### JWT invalide / sessions expirées

Si toutes les sessions utilisateur sont invalidées après un redémarrage, vérifiez que `JWT_SECRET` n'a pas changé (il est rechargé depuis `.env` à chaque boot). Tout changement de `JWT_SECRET` invalide tous les tokens actifs — c'est le comportement attendu.

### Permissions refusées sur `storage/` ou `database/`

```bash
# Community
sudo chown -R www-data:www-data storage/ database/ logs/
sudo chmod 755 storage/ database/ logs/
sudo chmod 644 database/*.sqlite

# SaaS
docker compose exec php chown -R www-data:www-data /var/www/html/storage
```

---

## Variables d'environnement — référence complète

| Variable | Mode | Requis | Valeur par défaut | Description |
|---|---|---|---|---|
| `APP_NAME` | Les deux | Non | `ZenCoParent` | Nom affiché |
| `APP_MODE` | Les deux | Oui | — | `community` ou `saas` |
| `APP_ENV` | Les deux | Oui | — | `production` ou `development` |
| `APP_URL` | Les deux | Oui | — | URL publique de l'application |
| `APP_SECRET` | Les deux | Oui | — | Secret applicatif (min. 32 chars) |
| `APP_DEBUG` | Les deux | Non | `false` | Activer les traces d'erreurs |
| `JWT_SECRET` | Les deux | Oui | — | Clé de signature JWT |
| `JWT_EXPIRY` | Les deux | Non | `3600` | Durée du token en secondes |
| `JWT_REFRESH_EXPIRY` | Les deux | Non | `2592000` | Durée du refresh token (30 j) |
| `CSRF_SECRET` | Les deux | Oui | — | Clé CSRF double-submit |
| `DB_CONNECTION` | Les deux | Oui | — | `pgsql` (SaaS) ou `sqlite` (Community) |
| `DB_FILE` | Community | Non | `database/zencoparent.sqlite` | Chemin SQLite |
| `DB_HOST` | SaaS | Oui | `postgres` | Hôte PostgreSQL |
| `DB_PORT` | SaaS | Non | `5432` | Port PostgreSQL |
| `DB_DATABASE` | SaaS | Oui | — | Nom de la base PostgreSQL |
| `DB_USERNAME` | SaaS | Oui | — | Utilisateur PostgreSQL |
| `DB_PASSWORD` | SaaS | Oui | — | Mot de passe PostgreSQL |
| `REDIS_HOST` | SaaS | Oui | `redis` | Hôte Redis |
| `REDIS_PORT` | SaaS | Non | `6379` | Port Redis |
| `REDIS_PASSWORD` | SaaS | Non | `null` | Mot de passe Redis |
| `REDIS_DB` | SaaS | Non | `0` | Index de la base Redis |
| `MINIO_ENDPOINT` | SaaS | Oui | — | URL MinIO (ex: `http://minio:9000`) |
| `MINIO_ACCESS_KEY` | SaaS | Oui | — | Clé d'accès MinIO |
| `MINIO_SECRET_KEY` | SaaS | Oui | — | Clé secrète MinIO |
| `MINIO_BUCKET` | SaaS | Oui | `zencoparent` | Nom du bucket |
| `MINIO_REGION` | SaaS | Non | `us-east-1` | Région MinIO/S3 |
| `STORAGE_PATH` | Community | Non | `storage/` | Chemin stockage local |
| `STORAGE_URL` | Community | Non | `/storage` | URL publique du stockage |
| `LICENSE_MASTER_KEY` | SaaS | Oui | — | Clé maître pour dériver les clés d'activation |
| `RATE_LIMIT_REQUESTS` | SaaS | Non | `60` | Requêtes max par fenêtre |
| `RATE_LIMIT_WINDOW` | SaaS | Non | `60` | Taille de la fenêtre en secondes |
| `GOOGLE_CLIENT_ID` | Les deux | Non | — | OAuth Google (optionnel) |
| `GOOGLE_CLIENT_SECRET` | Les deux | Non | — | OAuth Google (optionnel) |
| `GOOGLE_REDIRECT_URI` | Les deux | Non | — | Callback OAuth Google |

---

## Support

- Documentation : [github.com/frenchtux/ZenCoParent/wiki](https://github.com/frenchtux/ZenCoParent/wiki)
- Issues : [github.com/frenchtux/ZenCoParent/issues](https://github.com/frenchtux/ZenCoParent/issues)
- Licence : voir [LICENSE](LICENSE)
