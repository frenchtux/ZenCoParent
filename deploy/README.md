# Déployer ZenCoParent

Deux fichiers suffisent : `docker-compose.yml` et `.env`. Les images sont construites directement depuis GitHub, sans copier les sources sur le serveur.

## Prérequis

- Docker avec Compose v2.23 ou plus récent
- `git` sur l'hôte (Docker s'en sert pour récupérer les sources)
- Un nom de domaine dont le DNS pointe vers le serveur
- Les ports **80** et **443** libres sur l'hôte et ouverts depuis Internet

Aucun reverse proxy à installer : le service `caddy` du compose obtient et renouvelle tout seul le certificat Let's Encrypt du domaine d'`APP_URL`, et redirige HTTP vers HTTPS.

## Installation

```bash
mkdir zencoparent && cd zencoparent
curl -fsSLO https://raw.githubusercontent.com/frenchtux/ZenCoParent/main/deploy/docker-compose.yml
curl -fsSL  https://raw.githubusercontent.com/frenchtux/ZenCoParent/main/deploy/.env.example -o .env
```

Remplir dans `.env` : `APP_URL` (ex. `https://coparent.mondomaine.fr`), puis `APP_SECRET`, `JWT_SECRET`, `CSRF_SECRET` et `DB_PASSWORD` (`openssl rand -hex 32` pour chacun).

```bash
docker compose up -d --build
```

Au premier démarrage, les migrations s'appliquent et un admin est créé :

| Tenant | Login | Mot de passe |
|---|---|---|
| `zencoparent` | `admin@zencoparent.local` | `Admin1234!` |

Le certificat est obtenu en quelques secondes au premier démarrage ; en cas de souci, `docker compose logs caddy` indique pourquoi (DNS pas encore propagé, port 80 fermé…).

Le changement d'identifiants est imposé à la première connexion. Les redémarrages suivants ne touchent plus à ce compte.

Configurer ensuite SMTP, OAuth Google et sécurité depuis **Paramètres** dans l'interface admin.

## Mise à jour

```bash
docker compose up -d --build
```

Pour figer une version, remplacer `#main` par un tag dans `ZENCO_SOURCE` (ex. `#v1.0.0`).

## Reverse proxy déjà en place

Si un autre proxy occupe déjà 80/443 sur l'hôte (Traefik, nginx, Caddy partagé…), le plus simple est de l'arrêter et de laisser celui du compose gérer ZenCoParent. Pour garder le proxy existant, ajouter dans `.env` :

```env
CADDY_ADDRESS=:80                # Caddy ne fait plus de HTTPS, il relaie en HTTP
HTTP_PORT=8080                   # port vers lequel pointer le proxy existant
HTTPS_PORT=8443                  # inutilisé, mais doit rester libre
TRUSTED_PROXIES=private_ranges   # IP du proxy existant, ou plage (ex. 10.0.0.5/32)
```

`APP_URL` garde l'adresse publique en `https://`. `TRUSTED_PROXIES` permet de conserver l'IP réelle des visiteurs transmise par le proxy dans `X-Forwarded-For` ; sans elle, le rate limiting compterait toutes les requêtes comme venant du proxy.

## Sauvegarde

Les données sont dans les volumes `pgdata` (base) et `storage` (photos, pièces jointes). `caddy_data` contient les certificats, recréés automatiquement s'il est perdu.

```bash
docker compose exec -T postgres pg_dump -U zencoparent zencoparent > backup.sql
```

Conserver aussi `.env` : sans le même `APP_SECRET`, les secrets enregistrés en base (mot de passe SMTP, OAuth) deviennent illisibles.
