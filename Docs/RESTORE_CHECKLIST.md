# Database Restore Smoke Checklist

Use this checklist after restoring a `pg_dump` backup to verify the application is healthy.

## Prerequisites

- PostgreSQL 16 client tools (`pg_restore`, `psql`)
- Access to backup file (local `storage/app/backups/` or B2 `backups/` prefix)
- Docker stack running: `docker compose up -d`

## Restore steps

1. **Stop app traffic** (optional): scale down queue worker or stop Caddy briefly.
2. **Restore database** (example — adjust paths/credentials):

```bash
docker compose exec -T db pg_restore \
  -U onpoint -d onpoint_call --clean --if-exists \
  < backup-YYYY-MM-DD_HHMMSS.dump
```

Or from host with copied file:

```bash
pg_restore -h localhost -U onpoint -d onpoint_call --clean --if-exists backup.dump
```

3. **Run migrations** (safe if backup is from same schema version):

```bash
docker compose exec app php artisan migrate --force
```

## Smoke checklist

| Check | Command / URL | Expected |
|-------|----------------|----------|
| App health | `curl -f http://localhost/up` | HTTP 200 |
| Admin login | `/admin` | Filament login loads |
| Agent login | `/agent/login` | Agent sign-in loads |
| Company context | `docker compose exec app php artisan tinker --execute="echo App\Models\Company::count();"` | ≥ 1 company |
| Leads present | `docker compose exec app php artisan tinker --execute="echo App\Models\Lead::withoutGlobalScopes()->count();"` | Matches expected count |
| Queue worker | `docker compose ps queue` | Running |
| Scheduler | `docker compose exec app php artisan schedule:list` | Shows `claims:expire`, `dashboard:email-digest`, `db:backup` |
| Tests (optional) | `docker compose exec app php artisan test` | All green |

## Post-restore

- Verify agent can sign in and **Get Next Lead** returns a lead or valid empty-state message.
- Verify manager dashboard widgets poll without errors.
- Confirm no secrets were committed to git (`.env` only on server).

## Backup schedule

- Nightly `db:backup` at 02:00 server time (see `bootstrap/app.php`)
- Retention: 30 days on B2 (`BACKUP_RETENTION_DAYS`)
- Manual backup: `docker compose exec app php artisan db:backup --local-only`
