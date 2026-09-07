# Deploy GitHub master to local Docker, production, or both.
# Does not wipe the database. Stops if the working tree has unsaved git changes.
#
#   powershell -File scripts/deploy.ps1 both
#   powershell -File scripts/deploy.ps1 local
#   powershell -File scripts/deploy.ps1 prod
#
# Production uses SSH host alias `onpoint-prod` (see ~/.ssh/config).
param(
    [Parameter(Position = 0, Mandatory = $true)]
    [ValidateSet("local", "prod", "both")]
    [string]$Target
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $Root

$ProdSshHost = "onpoint-prod"
$Release = Join-Path $Root "scripts\prod-release.sh"

function Invoke-Checked {
    param(
        [Parameter(Mandatory, Position = 0)]
        [string]$Command,
        [Parameter(ValueFromRemainingArguments)]
        [string[]]$Arguments
    )
    & $Command @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "$Command $($Arguments -join ' ') failed (exit $LASTEXITCODE)"
    }
}

function Invoke-Compose {
    param([string[]]$Arguments)
    $all = @("compose") + $Arguments
    Invoke-Checked docker @all
}

function Assert-CleanWorktree {
    $dirty = git status --porcelain
    if ($dirty) {
        throw @"
Unsaved local git changes. Commit or stash first, then redeploy.

$dirty
"@
    }
}

function Assert-ProdSshAlias {
    $dump = & ssh -G $ProdSshHost 2>$null
    if ($LASTEXITCODE -ne 0 -or -not $dump) {
        throw @"
SSH host '$ProdSshHost' is not configured. Add this to $env:USERPROFILE\.ssh\config:

Host onpoint-prod
  HostName 86.48.30.69
  User root
  IdentityFile ~/.ssh/id_ed25519

Then test with: ssh onpoint-prod
"@
    }
}

function Invoke-LocalDeploy {
    Write-Host "==== LOCAL: SYNC origin/master ===="
    Invoke-Checked git fetch origin
    Invoke-Checked git checkout master
    Invoke-Checked git pull --ff-only origin master
    git log -1 --oneline

    Write-Host "==== LOCAL: DOCKER ===="
    Invoke-Compose @("up", "-d")

    Write-Host "==== LOCAL: COMPOSER ===="
    Invoke-Compose @("exec", "-T", "app", "composer", "install", "--no-interaction")

    Write-Host "==== LOCAL: MIGRATE ===="
    Invoke-Compose @("exec", "-T", "app", "php", "artisan", "migrate", "--force")

    Write-Host "==== LOCAL: CACHE ===="
    Invoke-Compose @("exec", "-T", "-u", "root", "app", "php", "artisan", "filament:assets")
    Invoke-Compose @("exec", "-T", "-u", "root", "app", "php", "artisan", "livewire:publish", "--assets")
    Invoke-Compose @("exec", "-T", "app", "php", "artisan", "icons:cache")
    Invoke-Compose @("exec", "-T", "app", "php", "artisan", "filament:cache-components")
    Invoke-Compose @("exec", "-T", "app", "php", "artisan", "optimize:clear")

    Write-Host "==== LOCAL: HEALTH ===="
    Start-Sleep -Seconds 2
    foreach ($check in @(
        @{ Name = "up"; Url = "http://localhost/up" },
        @{ Name = "admin"; Url = "http://localhost/admin/login" },
        @{ Name = "agent"; Url = "http://localhost/agent/login" }
    )) {
        try {
            $response = Invoke-WebRequest -Uri $check.Url -UseBasicParsing -TimeoutSec 15
            Write-Host ("{0}:{1}" -f $check.Name, [int]$response.StatusCode)
        } catch {
            throw "Local health check $($check.Name) failed for $($check.Url): $_"
        }
    }
}

function Invoke-ProdDeploy {
    if (-not (Test-Path $Release)) {
        throw "Missing $Release"
    }
    Assert-ProdSshAlias

    $remoteScript = "/tmp/prod-release.sh"
    $sshOpts = @("-o", "BatchMode=yes", "-o", "ConnectTimeout=15")

    Write-Host "==== PROD: $ProdSshHost from origin/master ===="
    Invoke-Checked scp @($sshOpts + @($Release, "${ProdSshHost}:${remoteScript}"))
    Invoke-Checked ssh @($sshOpts + @($ProdSshHost, "sed -i 's/\r$//' $remoteScript && bash $remoteScript"))
}

Assert-CleanWorktree

if ($Target -eq "local" -or $Target -eq "both") {
    Invoke-LocalDeploy
}

if ($Target -eq "prod" -or $Target -eq "both") {
    Invoke-ProdDeploy
}

Write-Host "Deploy ($Target) complete."
