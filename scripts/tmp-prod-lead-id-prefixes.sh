#!/bin/bash
set -euo pipefail
cd /opt/onpointcall

echo '==== EXTERNAL ID PREFIXES ===='
docker compose exec -T db psql -U onpoint -d onpoint_call -c \
"SELECT lead_type, left(external_lead_id, 3) AS prefix, COUNT(*) \
 FROM leads \
 WHERE external_lead_id IS NOT NULL AND external_lead_id <> '' \
 GROUP BY 1, 2 ORDER BY 1, 3 DESC;"

echo '==== SAMPLE IDS ===='
docker compose exec -T db psql -U onpoint -d onpoint_call -c \
"SELECT lead_type, external_lead_id, booking_id, phone \
 FROM leads \
 WHERE external_lead_id IS NOT NULL AND external_lead_id <> '' \
 ORDER BY lead_type, id \
 LIMIT 20;"
