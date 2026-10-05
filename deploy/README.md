# Déployer ZenCoParent

Deux fichiers suffisent : `docker-compose.yml` et `.env`. Les images sont construites directement depuis GitHub, sans copier les sources sur le serveur.

## Prérequis

- Docker avec Compose v2
- `git` sur l'hôte (Docker s'en sert pour récupérer les sources)
- Un reverse proxy HTTPS devant (Caddy, Traefik, nginx…) : les cookies de session sont `Secure` et ne passent pas en HTTP, sauf sur `localhost`

## Installation

```bash
mkdir zencoparent && cd zencoparent
curl -fsSLO https://raw.githubusercontent.com/frenchtux/ZenCoParent/main/deploy/docker-compose.yml
curl -fsSL  https://raw.githubusercontent.com/frenchtux/ZenCoParent/main/deploy/.env.example -o .env
```

Remplir dans `.env` : `APP_URL`, `APP_SECRET`, `JWT_SECRET`, `CSRF_SECRET` et `DB_PASSWORD` (`openssl rand -hex 32` pour chacun).

```bash
docker compose up -d --build
```

Au premier démarrage, les migrations s'appliquent et un admin est créé :

| Tenant | Login | Mot de passe |
|---|---|---|
| `zencoparent` | `admin@zencoparent.local` | `Admin1234!` |

Le changement d'identifiants est imposé à la première connexion. Les redémarrages suivants ne touchent plus à ce compte.

Configurer ensuite SMTP, OAuth Google et sécurité depuis **Paramètres** dans l'interface admin.

## Mise à jour

```bash
docker compose up -d --build
```

Pour figer une version, remplacer `#main` par un tag dans `ZENCO_SOURCE` (ex. `#v1.0.0`).

## Sauvegarde

Deux volumes contiennent les données : `pgdata` (base) et `storage` (photos, pièces jointes).

```bash
docker compose exec -T postgres pg_dump -U zencoparent zencoparent > backup.sql
```

Conserver aussi `.env` : sans le même `APP_SECRET`, les secrets enregistrés en base (mot de passe SMTP, OAuth) deviennent illisibles.
