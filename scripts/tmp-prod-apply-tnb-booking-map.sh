#!/bin/bash
set -euo pipefail
cd /opt/onpointcall

echo '==== BEFORE ===='
docker compose exec -T db psql -U onpoint -d onpoint_call -c \
"SELECT id, jsonb_typeof(booking_param_map) AS map_kind, booking_url_template, booking_param_map FROM app_settings;"
docker compose exec -T db psql -U onpoint -d onpoint_call -c \
"SELECT id, name, lead_type, booking_url_template, jsonb_typeof(booking_param_map) AS map_kind, booking_param_map FROM calling_lists ORDER BY id;"

echo '==== APPLY COMPANY BOOKING PARAM MAP ===='
docker compose exec -T app php scripts/tmp-prod-apply-tnb-booking-map.php

echo '==== AFTER ===='
docker compose exec -T db psql -U onpoint -d onpoint_call -c \
"SELECT id, jsonb_typeof(booking_param_map) AS map_kind, booking_url_template, booking_param_map FROM app_settings;"
docker compose exec -T db psql -U onpoint -d onpoint_call -c \
"SELECT id, name, lead_type, booking_url_template, jsonb_typeof(booking_param_map) AS map_kind, booking_param_map FROM calling_lists ORDER BY id;"
