#!/bin/bash
set -euo pipefail
cd /opt/onpointcall

docker compose exec -T db psql -U onpoint -d onpoint_call -c \
"SELECT id, phone, home_owner, extra_fields, left(qualification_result::text, 2000) AS qual_preview
 FROM leads WHERE phone = '5166956824';"
