# OnPoint Call Center

Laravel + Filament + PostgreSQL call center application for OnPoint Marketing.

## Documentation

**Build only from the architecture plan.** Older specs are archived to avoid confusion (e.g. MySQL vs PostgreSQL).

| Doc | Role |
|-----|------|
| [plans/call-center-architecture.md](plans/call-center-architecture.md) | **Source of truth** — stack, data model, locked owner decisions |
| [Docs/COMMANDS.md](Docs/COMMANDS.md) | Copy-paste Docker / artisan / npm commands |
| [Docs/REQUIREMENTS_chatgpt.md](Docs/REQUIREMENTS_chatgpt.md) | Requirements input (architecture wins on conflict) |
| [Docs/SoftScore/soft-score-api.md](Docs/SoftScore/soft-score-api.md) | Soft Score API integration |
| [Docs/Archive/](Docs/Archive/) | Superseded specs — reference only, do not implement |

## Stack

| Layer | Choice |
|-------|--------|
| App | Laravel 13 (PHP 8.4) |
| Admin | Filament 4 |
| Agent UI | Livewire + Blade + Tailwind |
| Database | PostgreSQL 16 |
| Auth | Laravel Socialite (Google + Microsoft) |
| Proxy / TLS | Caddy |
| Queue | Laravel database driver |

## Quick start (Docker)

Requires [Docker](https://docs.docker.com/get-docker/) and Docker Compose.

```bash
# 1. Install Laravel + Filament + Socialite (first time only)
./scripts/bootstrap.sh          # Linux / macOS / WSL
# or
.\scripts\bootstrap.ps1         # Windows PowerShell

# 2. Configure environment
cp .env.example .env
# Set APP_URL, DB_* (defaults match docker-compose.yml), OAuth client IDs

# 3. Start services
docker compose up -d --build

# 4. Initialize app
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan filament:install --panels
```

Open http://localhost (or your `APP_DOMAIN`).

## How to test locally (Windows + Docker)

```bash
cd D:\sf\OnPointCall
docker compose up -d --build
npm install && npm run build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
docker compose exec -u root app php artisan filament:assets
docker compose exec -u root app php artisan livewire:publish --assets
docker compose exec app php artisan icons:cache
docker compose exec app php artisan filament:cache-components
docker compose exec app php artisan optimize:clear
```

`vendor/` and compiled views run from Linux Docker volumes (not the Windows bind mount). Edit code on `D:\` as usual — no local deploy. After `composer.lock` changes, run `docker compose exec app composer install`.

Do **not** run `php artisan optimize` locally. It caches config/routes, can 500 Filament after login, and can make `php artisan test` wipe Postgres. Use `icons:cache` / `filament:cache-components` instead.

| Check | URL / command |
|---|---|
| Health | http://localhost/up |
| Sign in | http://localhost/ |
| Admin | http://localhost/admin |
| Agent window | http://localhost/agent |
| Tests | `docker compose exec app php artisan test` |

Sign in: `jason.paine@onpointcall.com` / `password`

### Invite a user by email

The app can email a temporary password. Mail is `log` until you set SMTP in `.env` (see `.env.example` for Resend).

```bash
docker compose exec app php artisan user:invite jasonpaine1@gmail.com --name="Jason Paine" --role=admin --lists=Standard
```

Or in Admin → Users → **Invite user**. Without SMTP, the message is written to `storage/logs/laravel.log` instead of being delivered.

Tests use sqlite `:memory:` only. They will not touch Docker Postgres. If admin login says credentials do not match, re-seed (`db:seed --force`) — the user was missing, not the password.

If `/admin` is a white screen or the Sign in button sticks: hard-refresh, republish assets (`filament:assets` + `livewire:publish --assets`), and run `optimize:clear`. First boot after a volume recreate runs `composer install` inside the container and can take a minute.

## Services

| Service | Purpose |
|---------|---------|
| `caddy` | HTTPS reverse proxy → PHP-FPM |
| `app` | Laravel (php-fpm) |
| `queue` | `php artisan queue:work` |
| `db` | PostgreSQL 16 |

## Development without Docker

Install PHP 8.3+, Composer, and PostgreSQL 16 locally. Run `composer install` after bootstrap, point `.env` at your local Postgres, then `php artisan serve`.

## Secrets

Never commit `.env` or API credentials. Soft Score OAuth secrets belong in environment variables only.
