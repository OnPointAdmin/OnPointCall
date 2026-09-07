# Update this Windows/Docker checkout to origin/master and apply migrations.
# Forward-migrate only. Never wipe the database.
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $Root

$dirty = git status --porcelain
if ($dirty) {
    Write-Error "Uncommitted changes. Commit or stash before deploy-local."
    git status -sb
    exit 1
}

Write-Host "==== LOCAL PULL ===="
git fetch origin master
git checkout master
git pull origin master
git log -1 --oneline

$running = docker compose ps --status running --services
if ($LASTEXITCODE -ne 0 -or ($running -notcontains "app")) {
    Write-Error "Docker app is not running. Start it with: docker compose up -d"
    exit 1
}

Write-Host "==== LOCAL MIGRATE ===="
docker compose exec -T app php artisan migrate --force
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan icons:cache
docker compose exec -T app php artisan filament:cache-components

Write-Host "==== LOCAL DONE ===="
git log -1 --oneline
Write-Host "Open http://localhost/admin"
