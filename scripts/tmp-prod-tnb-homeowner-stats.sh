#!/bin/bash
set -euo pipefail
cd /opt/onpointcall

echo '==== HOME OWNER / EXTRA ON TNB ===='
docker compose exec -T db psql -U onpoint -d onpoint_call -c \
"SELECT
   COUNT(*) AS tnb_leads,
   COUNT(*) FILTER (WHERE home_owner IS NOT NULL AND home_owner <> '') AS has_home_owner,
   COUNT(*) FILTER (WHERE extra_fields IS NOT NULL AND extra_fields::text NOT IN ('','null','{}')) AS has_extra
 FROM leads WHERE lead_type = 'tnb';"

echo '==== HOME OWNER VALUES ===='
docker compose exec -T db psql -U onpoint -d onpoint_call -c \
"SELECT home_owner, COUNT(*) FROM leads WHERE lead_type = 'tnb' GROUP BY 1 ORDER BY 2 DESC;"

echo '==== TNB IMPORT MAP ===='
docker compose exec -T db psql -U onpoint -d onpoint_call -c \
"SELECT name, column_map FROM import_mappings WHERE lead_type = 'tnb';"
