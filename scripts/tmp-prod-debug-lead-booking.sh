#!/bin/bash
set -euo pipefail
cd /opt/onpointcall

echo '==== LEAD ===='
docker compose exec -T db psql -U onpoint -d onpoint_call -c \
"SELECT id, phone, phone_2, first_name, last_name, email, address, address_2, city, state, zip,
        age_range, annual_income, marital_status, gender, home_owner, soft_score_code,
        external_lead_id, booking_id, status, lead_type, calling_list_id,
        qualification_status, soft_score_status, dnc_status
 FROM leads WHERE phone = '5166956824' OR phone_2 = '5166956824' OR phone LIKE '%5166956824%' OR phone_2 LIKE '%5166956824%';"

echo '==== CALLING LIST + SETTINGS ===='
docker compose exec -T db psql -U onpoint -d onpoint_call -c \
"SELECT l.id AS lead_id, l.calling_list_id, cl.name AS list_name, cl.lead_type, cl.booking_url_template AS list_url,
        jsonb_typeof(cl.booking_param_map) AS list_map_kind, cl.booking_param_map AS list_map,
        s.booking_url_template AS settings_url, jsonb_typeof(s.booking_param_map) AS settings_map_kind,
        s.booking_param_map AS settings_map
 FROM leads l
 LEFT JOIN calling_lists cl ON cl.id = l.calling_list_id
 LEFT JOIN app_settings s ON s.company_id = l.company_id
 WHERE l.phone = '5166956824' OR l.phone_2 = '5166956824';"

echo '==== BUILD URL ===='
docker compose exec -T app php scripts/tmp-prod-debug-lead-booking.php
