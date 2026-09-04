#!/usr/bin/env bash
# Idempotent Cloud Agent install step for the OnPoint Call app.
# Runs from the repo root after a fresh checkout. Prepares dependencies,
# environment file, and the PostgreSQL schema/seed data. Must terminate.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "==> Ensuring PostgreSQL 16 is running (needed for migrations)"
sudo pg_ctlcluster 16 main start 2>/dev/null || true
for _ in $(seq 1 30); do
  if pg_isready -h 127.0.0.1 -U onpoint -d onpoint_call >/dev/null 2>&1; then
    break
  fi
  sleep 1
done
pg_isready -h 127.0.0.1 -U onpoint -d onpoint_call

echo "==> Preparing .env (gitignored, recreated after checkout)"
if [ ! -f .env ]; then
  cp .env.example .env
fi
# Native dev uses local PostgreSQL and the artisan dev server.
sed -i 's/^DB_HOST=db$/DB_HOST=127.0.0.1/' .env
sed -i 's#^APP_URL=.*#APP_URL=http://localhost:8000#' .env

echo "==> Installing PHP dependencies"
composer install --no-interaction --prefer-dist --no-progress

echo "==> Ensuring application key"
if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

echo "==> Installing and building front-end assets"
npm ci
npm run build

echo "==> Running database migrations"
php artisan migrate --force

echo "==> Seeding reference data (only if not already seeded)"
SEEDED="$(PGPASSWORD=secret psql -h 127.0.0.1 -U onpoint -d onpoint_call -tAc \
  "SELECT COUNT(*) FROM users WHERE email='jason.paine@onpointmrg.com'" 2>/dev/null || echo 0)"
if [ "${SEEDED:-0}" = "0" ]; then
  php artisan db:seed --force
else
  echo "    Seed data already present, skipping."
fi

echo "==> Clearing caches (never run 'php artisan optimize' locally)"
php artisan optimize:clear

echo "==> Install complete."
