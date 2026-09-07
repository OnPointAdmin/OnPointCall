# Usage: powershell -File scripts/deploy.ps1 [local|prod|both]
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$Target = if ($args.Count -gt 0) { $args[0] } else { "both" }

switch ($Target) {
    "local" { & "$Root\scripts\deploy-local.ps1" }
    "prod" { & "$Root\scripts\deploy-prod.ps1" }
    "both" {
        & "$Root\scripts\deploy-local.ps1"
        & "$Root\scripts\deploy-prod.ps1"
    }
    default {
        Write-Error "Usage: scripts/deploy.ps1 [local|prod|both]"
        exit 1
    }
}
