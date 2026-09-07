#!/usr/bin/env bash
# From a local machine: SSH to prod and reset /opt/onpointcall to origin/master.
# Requires ~/.ssh/config Host onpoint-prod (or ONPOINT_PROD_SSH).
# Forward-migrate only. Never wipe the database.
set -euo pipefail

HOST="${ONPOINT_PROD_SSH:-onpoint-prod}"

if ! ssh -o BatchMode=yes -o ConnectTimeout=8 "$HOST" 'true'; then
    echo "Cannot SSH to $HOST." >&2
    echo "Add this once to ~/.ssh/config, then retry:" >&2
    echo "" >&2
    echo "Host onpoint-prod" >&2
    echo "  HostName YOUR_VPS_IP_OR_DOMAIN" >&2
    echo "  User YOUR_SSH_USER" >&2
    echo "  IdentityFile ~/.ssh/id_ed25519" >&2
    echo "" >&2
    echo "Or set ONPOINT_PROD_SSH to an existing SSH host alias." >&2
    exit 1
fi

echo "==== PROD SYNC via $HOST ===="
ssh -t "$HOST" 'bash -s' <<'REMOTE'
set -euo pipefail
cd /opt/onpointcall
echo "==== BEFORE ===="
git log -1 --oneline
git status -sb
git fetch origin
git reset --hard origin/master
bash scripts/prod-update.sh
REMOTE

echo "==== PROD DONE ===="
