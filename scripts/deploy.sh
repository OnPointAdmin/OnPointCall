#!/usr/bin/env bash
# Usage: scripts/deploy.sh [local|prod|both]
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TARGET="${1:-both}"

case "$TARGET" in
    local)
        bash "$ROOT/scripts/deploy-local.sh"
        ;;
    prod)
        bash "$ROOT/scripts/deploy-prod.sh"
        ;;
    both)
        bash "$ROOT/scripts/deploy-local.sh"
        bash "$ROOT/scripts/deploy-prod.sh"
        ;;
    *)
        echo "Usage: scripts/deploy.sh [local|prod|both]" >&2
        exit 1
        ;;
esac
