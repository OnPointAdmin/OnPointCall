# OnPoint Call Center

Laravel + Filament + PostgreSQL call center application for OnPoint Marketing.

## Documentation

**Build only from the architecture plan.** Older specs are archived to avoid confusion (e.g. MySQL vs PostgreSQL).

| Doc | Role |
|-----|------|
| [plans/call-center-architecture.md](plans/call-center-architecture.md) | **Source of truth** — stack, data model, locked owner decisions |
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
