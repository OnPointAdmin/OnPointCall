#!/usr/bin/env bash
# Update this Windows/Docker checkout to origin/master and apply migrations.
# Forward-migrate only. Never wipe the database.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ -n "$(git status --porcelain)" ]]; then
    echo "Uncommitted changes. Commit or stash before deploy-local." >&2
    git status -sb
    exit 1
fi

echo "==== LOCAL PULL ===="
git fetch origin master
git checkout master
git pull origin master
git log -1 --oneline

if ! docker compose ps --status running --services 2>/dev/null | grep -qx app; then
    echo "Docker app is not running. Start it with: docker compose up -d" >&2
    exit 1
fi

echo "==== LOCAL MIGRATE ===="
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan icons:cache
docker compose exec -T app php artisan filament:cache-components

echo "==== LOCAL DONE ===="
git log -1 --oneline
echo "Open http://localhost/admin"
