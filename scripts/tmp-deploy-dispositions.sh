#!/bin/bash
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

echo '==== MIGRATE ===='
docker compose exec -T app php artisan migrate --force

echo '==== CACHE ===='
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan icons:cache
docker compose exec -T app php artisan filament:cache-components

echo '==== RESTART ===='
docker compose restart app queue

echo '==== AFTER ===='
git log -1 --oneline
sleep 3
curl -fsS -o /dev/null -w 'up:%{http_code}\n' http://localhost/up
curl -fsS -o /dev/null -w 'admin:%{http_code}\n' http://localhost/admin/login

echo '==== DISPOSITIONS TABLE ===='
docker compose exec -T db psql -U onpoint -d onpoint_call -c "SELECT COUNT(*) AS disposition_rows FROM dispositions;"
