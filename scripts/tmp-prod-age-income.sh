#!/bin/bash
set -euo pipefail
cd /opt/onpointcall

echo '==== THIS LEAD ===='
docker compose exec -T db psql -U onpoint -d onpoint_call -c \
"SELECT id, phone, age_range, annual_income, extra_fields FROM leads WHERE phone = '5166956824';"

echo '==== TNB AGE / INCOME COVERAGE ===='
docker compose exec -T db psql -U onpoint -d onpoint_call -c \
"SELECT
   COUNT(*) AS tnb_leads,
   COUNT(*) FILTER (WHERE age_range IS NOT NULL AND age_range <> '') AS has_age,
   COUNT(*) FILTER (WHERE annual_income IS NOT NULL AND annual_income <> '') AS has_income
 FROM leads WHERE lead_type = 'tnb';"

echo '==== TNB AGE VALUES ===='
docker compose exec -T db psql -U onpoint -d onpoint_call -c \
"SELECT age_range, COUNT(*) FROM leads WHERE lead_type = 'tnb' GROUP BY 1 ORDER BY 2 DESC;"

echo '==== TNB INCOME VALUES ===='
docker compose exec -T db psql -U onpoint -d onpoint_call -c \
"SELECT annual_income, COUNT(*) FROM leads WHERE lead_type = 'tnb' GROUP BY 1 ORDER BY 2 DESC;"

echo '==== BATCH 1 COLUMN MAP ===='
docker compose exec -T db psql -U onpoint -d onpoint_call -c \
"SELECT id, source_filename, lead_type, column_map->>'age_range' AS age_col, column_map->>'annual_income' AS income_col FROM import_batches ORDER BY id;"
