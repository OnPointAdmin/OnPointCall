#!/usr/bin/env bash
# Run on the production VPS after git is already at origin/master.
# Forward-migrate only. Never wipe the database.
set -euo pipefail

cd /opt/onpointcall

echo "==== PROD UPDATE ===="
git log -1 --oneline
git status -sb

docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan icons:cache
docker compose exec -T app php artisan filament:cache-components
docker compose restart app queue

sleep 3
echo "==== HEALTH ===="
curl -fsS -o /dev/null -w "up:%{http_code}\n" http://localhost/up
echo "==== AFTER ===="
git log -1 --oneline
