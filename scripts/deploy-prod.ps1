# From Windows: SSH to prod and reset /opt/onpointcall to origin/master.
# Requires ~/.ssh/config Host onpoint-prod (or ONPOINT_PROD_SSH).
# Forward-migrate only. Never wipe the database.
$ErrorActionPreference = "Stop"

$HostAlias = if ($env:ONPOINT_PROD_SSH) { $env:ONPOINT_PROD_SSH } else { "onpoint-prod" }

ssh -o BatchMode=yes -o ConnectTimeout=8 $HostAlias "true"
if ($LASTEXITCODE -ne 0) {
    Write-Host "Cannot SSH to $HostAlias."
    Write-Host "Add this once to $HOME\.ssh\config, then retry:"
    Write-Host ""
    Write-Host "Host onpoint-prod"
    Write-Host "  HostName YOUR_VPS_IP_OR_DOMAIN"
    Write-Host "  User YOUR_SSH_USER"
    Write-Host "  IdentityFile ~/.ssh/id_ed25519"
    Write-Host ""
    Write-Host "Or set ONPOINT_PROD_SSH to an existing SSH host alias."
    exit 1
}

Write-Host "==== PROD SYNC via $HostAlias ===="
$remote = @"
set -euo pipefail
cd /opt/onpointcall
echo "==== BEFORE ===="
git log -1 --oneline
git status -sb
git fetch origin
git reset --hard origin/master
bash scripts/prod-update.sh
"@

$tmp = Join-Path $env:TEMP "onpoint-prod-update.sh"
Set-Content -Path $tmp -Value $remote -Encoding utf8
Get-Content -Raw $tmp | ssh $HostAlias "bash -s"
$code = $LASTEXITCODE
Remove-Item $tmp -ErrorAction SilentlyContinue
if ($code -ne 0) { exit $code }

Write-Host "==== PROD DONE ===="
