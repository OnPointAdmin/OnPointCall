#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ -f artisan ]]; then
    echo "Laravel already installed (artisan exists). Skipping create-project."
    exit 0
fi

echo "Checking Docker engine..."
if ! docker version >/dev/null 2>&1; then
    echo "Docker engine is not running. Start Docker Desktop and re-run this script." >&2
    exit 1
fi

echo "Creating Laravel project in staging directory..."
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$ROOT:/app" \
    -w /app \
    composer:2 \
    create-project laravel/laravel staging --prefer-dist --no-interaction

echo "Moving Laravel files to repo root..."
shopt -s dotglob
for item in staging/*; do
    base="$(basename "$item")"
    if [[ "$base" == "Docs" || "$base" == "plans" || "$base" == "docker" || "$base" == "scripts" ]]; then
        continue
    fi
    if [[ -e "$base" ]]; then
        echo "Skip existing: $base"
    else
        mv "$item" "$ROOT/"
    fi
done
shopt -u dotglob
rmdir staging 2>/dev/null || rm -rf staging

if [[ ! -f .env ]]; then
    cp .env.example .env
fi

echo "Building app image (PHP 8.4 + intl)..."
docker compose build app

echo "Installing Filament and Socialite..."
docker compose run --rm app composer require filament/filament:"^4.0" laravel/socialite --no-interaction

echo ""
echo "Bootstrap complete. Next steps:"
echo "  1. Edit .env (DB_*, APP_URL, OAuth keys)"
echo "  2. docker compose up -d --build"
echo "  3. docker compose exec app php artisan migrate"
echo "  4. docker compose exec app php artisan filament:install --panels --no-interaction"
