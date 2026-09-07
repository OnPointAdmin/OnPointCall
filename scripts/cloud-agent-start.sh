#!/usr/bin/env bash
# Per-boot Cloud Agent start step for the OnPoint Call app.
# Brings up PostgreSQL, launches the queue worker in the background, then
# runs the Laravel dev server in the foreground so it stays attached.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "==> Starting PostgreSQL 16"
sudo pg_ctlcluster 16 main start 2>/dev/null || true
for _ in $(seq 1 30); do
  if pg_isready -h 127.0.0.1 -U onpoint -d onpoint_call >/dev/null 2>&1; then
    break
  fi
  sleep 1
done
if ! pg_isready -h 127.0.0.1 -U onpoint -d onpoint_call >/dev/null 2>&1; then
  echo "PostgreSQL did not become ready" >&2
  exit 1
fi

echo "==> Starting queue worker (background)"
nohup php artisan queue:work --sleep=3 --tries=3 --timeout=300 \
  >> storage/logs/queue-worker.log 2>&1 &

echo "==> Starting Laravel dev server on http://0.0.0.0:8000 (foreground)"
exec php artisan serve --host=0.0.0.0 --port=8000
