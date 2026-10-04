#!/bin/sh
# Clone ou met à jour les sources depuis GitHub dans /var/www/html.
# Variables d'environnement :
#   GITHUB_REPO   — ex: frenchtux/ZenCoParent  ou URL complète
#   GITHUB_REF    — branche, tag ou commit SHA  (défaut: main)
#   GITHUB_TOKEN  — PAT pour repo privé         (optionnel)
set -e

DEST="/var/www/html"
REF="${GITHUB_REF:-main}"

# Construit l'URL du repo
if echo "$GITHUB_REPO" | grep -q "^https://\|^git@"; then
    REPO_URL="$GITHUB_REPO"
else
    if [ -n "$GITHUB_TOKEN" ]; then
        REPO_URL="https://${GITHUB_TOKEN}@github.com/${GITHUB_REPO}.git"
    else
        REPO_URL="https://github.com/${GITHUB_REPO}.git"
    fi
fi

echo "==> Repo  : $GITHUB_REPO"
echo "==> Ref   : $REF"
echo "==> Dest  : $DEST"

if [ -d "$DEST/.git" ]; then
    echo "==> Dépôt déjà présent — git fetch + checkout"
    git -C "$DEST" fetch origin
    git -C "$DEST" checkout "$REF"
    git -C "$DEST" reset --hard "origin/$REF" 2>/dev/null \
        || git -C "$DEST" reset --hard "$REF"
else
    echo "==> Clone initial"
    git clone --depth=1 --branch "$REF" "$REPO_URL" "$DEST"
fi

# Installe les dépendances PHP (sans dev, optimisé)
echo "==> composer install"
composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --working-dir="$DEST"

# Permissions
chown -R www-data:www-data "$DEST"

echo "==> Sources prêtes dans $DEST"
