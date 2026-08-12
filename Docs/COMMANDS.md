# Quick commands

Run from the repo root (`D:\sf\OnPointCall`). Almost everything goes through Docker:

```bash
docker compose exec app <command>
```

Edit code on `D:\`, save, refresh the browser. No local deploy.

| Check | URL |
|---|---|
| Health | http://localhost/up |
| Admin | http://localhost/admin |
| Agent | http://localhost/agent/login |

Admin login: `jason.paine@onpointcall.com` / `password`

---

## Daily

```bash
docker compose up -d
docker compose ps
docker compose logs -f app
docker compose logs -f queue
docker compose down
```

Rebuild after Dockerfile / compose / PHP ini changes:

```bash
docker compose up -d --build
```

---

## Database

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan migrate:status
docker compose exec app php artisan db:seed --force
```

---

## Tests

Tests use sqlite `:memory:` only. They will not touch Docker Postgres.

```bash
docker compose exec app php artisan test
docker compose exec app php artisan test --filter=AdminDashboardTest
```

---

## Frontend

```bash
npm install
npm run build
npm run dev
```

`npm run build` is what local Docker serves. Use `npm run dev` only if you want Vite HMR.

---

## Composer (inside the container)

`vendor/` is a Linux Docker volume, not the Windows folder. After `composer.json` / `composer.lock` changes:

```bash
docker compose exec app composer install
docker compose exec app composer require vendor/package
docker compose exec app composer update vendor/package
```

---

## Filament / white screen / stuck login

Do **not** run `php artisan optimize` locally. It caches config/routes, can 500 Filament after login, and can make tests wipe Postgres.

```bash
docker compose exec -u root app php artisan filament:assets
docker compose exec -u root app php artisan livewire:publish --assets
docker compose exec app php artisan icons:cache
docker compose exec app php artisan filament:cache-components
docker compose exec app php artisan optimize:clear
```

Then hard-refresh the browser.

---

## Users

```bash
docker compose exec app php artisan user:invite you@example.com --name="Name" --role=admin --lists=Standard
docker compose exec app php artisan user:invite you@example.com --name="Name" --role=manager --lists=Standard
docker compose exec app php artisan user:invite you@example.com --name="Name" --role=agent --lists=Standard
docker compose exec app php artisan user:invite you@example.com --name="Name" --role=agent --no-email
```

Roles: `admin`, `manager`, `agent`. `--lists` is comma-separated calling list names. Without SMTP, the invite is written to `storage/logs/laravel.log`.

Or: Admin → Users → **Invite user**.

---

## App jobs

```bash
docker compose exec app php artisan claims:expire
docker compose exec app php artisan dashboard:email-digest --force
docker compose exec app php artisan db:backup --local-only
docker compose exec app php artisan schedule:list
docker compose exec app php artisan queue:restart
```

Slim lead import (CSV path is inside the container, e.g. bind-mounted repo):

```bash
docker compose exec app php artisan leads:migrate-slim storage/app/import.csv --lead-type=standard
```

---

## Logs / tinker / shell

```bash
docker compose exec app tail -n 100 storage/logs/laravel.log
docker compose exec app php artisan tinker
docker compose exec app bash
docker compose exec db psql -U onpoint -d onpoint_call
```

```bash
docker compose exec app php artisan tinker --execute="echo App\Models\Company::count();"
docker compose exec app php artisan tinker --execute="echo App\Models\Lead::withoutGlobalScopes()->count();"
```

---

## Don’t

| Don’t | Use instead |
|---|---|
| `php artisan optimize` locally | `optimize:clear` + `icons:cache` + `filament:cache-components` |
| `composer install` on Windows for the running app | `docker compose exec app composer install` |
| `php artisan serve` while Docker is on port 80 | Docker + http://localhost |
