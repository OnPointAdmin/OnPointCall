#!/bin/bash
# Canonical production release. Runs on the VPS.
# Pulled from origin/master. Forward migrations only — never migrate:fresh / db:wipe.
set -euo pipefail
cd /opt/onpointcall

echo '==== BEFORE ===='
git log -1 --oneline
git status -sb

echo '==== SYNC ===='
git fetch origin
if [ -f Docs/Migration/LeadMaster.csv ]; then
  cp -a Docs/Migration/LeadMaster.csv /tmp/LeadMaster.csv.pre-deploy
fi
git reset --hard origin/master
git log -1 --oneline
git status -sb

echo '==== REBUILD CONTAINERS ===='
docker compose up -d --build

echo '==== COMPOSER ===='
docker compose exec -T app composer install --no-dev --optimize-autoloader --no-interaction

echo '==== MIGRATE ===='
docker compose exec -T app php artisan migrate --force

echo '==== ASSETS ===='
docker compose exec -T -u root app php artisan filament:assets
docker compose exec -T -u root app php artisan livewire:publish --assets
docker compose exec -T app php artisan icons:cache
docker compose exec -T app php artisan filament:cache-components
docker compose exec -T app php artisan optimize:clear

echo '==== RESTART ===='
docker compose restart app queue

echo '==== AFTER ===='
git log -1 --oneline

echo '==== HEALTH ===='
sleep 3
curl -fsS -o /dev/null -w 'up:%{http_code}\n' http://localhost/up
curl -fsS -o /dev/null -w 'admin:%{http_code}\n' http://localhost/admin/login
curl -fsS -o /dev/null -w 'agent:%{http_code}\n' http://localhost/agent/login
docker compose ps --format 'table {{.Name}}\t{{.Status}}'
