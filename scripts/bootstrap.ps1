# Bootstrap Laravel + packages using Docker (requires Docker Desktop or Docker Engine).
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $Root

if (Test-Path "artisan") {
    Write-Host "Laravel already installed (artisan exists). Skipping create-project."
    exit 0
}

Write-Host "Checking Docker engine..."
docker version | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Error "Docker engine is not running. Open Docker Desktop, wait until the whale icon is steady, then re-run this script."
    exit 1
}

Write-Host "Creating Laravel project in staging directory..."
docker run --rm `
    -v "${Root}:/app" `
    -w /app `
    composer:2 `
    create-project laravel/laravel staging --prefer-dist --no-interaction
if ($LASTEXITCODE -ne 0) {
    Write-Error "Laravel create-project failed. Fix Docker and re-run."
    exit 1
}

Write-Host "Moving Laravel files to repo root..."
$preserve = @("Docs", "plans", "docker", "scripts", "staging")
Get-ChildItem -Path "staging" -Force | ForEach-Object {
    if ($preserve -contains $_.Name) { return }
    $dest = Join-Path $Root $_.Name
    if (Test-Path $dest) {
        Write-Host "Skip existing: $($_.Name)"
    } else {
        Move-Item -Path $_.FullName -Destination $dest
    }
}
Remove-Item -Recurse -Force staging -ErrorAction SilentlyContinue

if (-not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
}

Write-Host "Installing Filament and Socialite..."
docker run --rm `
    -v "${Root}:/app" `
    -w /app `
    composer:2 `
    require filament/filament:"^3.2" laravel/socialite --no-interaction

Write-Host ""
Write-Host "Bootstrap complete. Next steps:"
Write-Host "  1. Edit .env (DB_*, APP_URL, OAuth keys)"
Write-Host "  2. docker compose up -d --build"
Write-Host "  3. docker compose exec app php artisan key:generate"
Write-Host "  4. docker compose exec app php artisan migrate"
Write-Host "  5. docker compose exec app php artisan filament:install --panels"
