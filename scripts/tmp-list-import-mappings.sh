#!/bin/bash
set -euo pipefail
cd /opt/onpointcall
grep -E 'APP_(ENV|URL|NAME)=' .env || true
echo '==== companies ===='
docker compose exec -T db psql -U onpoint -d onpoint_call -c 'SELECT id, name FROM companies;'
echo '==== import_mappings ===='
docker compose exec -T db psql -U onpoint -d onpoint_call -c 'SELECT id, company_id, name, lead_type, is_default, column_map FROM import_mappings ORDER BY id;'
