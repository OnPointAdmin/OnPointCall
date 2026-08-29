#!/bin/bash
set -euo pipefail
cd /opt/onpointcall

echo '==== TNB ID COLUMNS ===='
docker compose exec -T db psql -U onpoint -d onpoint_call -c \
"SELECT
   left(external_lead_id, 3) AS ext_prefix,
   left(booking_id, 3) AS book_prefix,
   COUNT(*)
 FROM leads WHERE lead_type = 'tnb'
 GROUP BY 1, 2 ORDER BY 3 DESC;"

echo '==== FRANKEL IDS ===='
docker compose exec -T db psql -U onpoint -d onpoint_call -c \
"SELECT external_lead_id, booking_id, extra_fields FROM leads WHERE phone = '5166956824';"

echo '==== TNB WITH 00Q ANYWHERE ===='
docker compose exec -T db psql -U onpoint -d onpoint_call -c \
"SELECT COUNT(*) FILTER (WHERE external_lead_id LIKE '00Q%') AS ext_00q,
        COUNT(*) FILTER (WHERE booking_id LIKE '00Q%') AS book_00q,
        COUNT(*) FILTER (WHERE extra_fields::text ILIKE '%00Q%') AS extra_00q
 FROM leads WHERE lead_type = 'tnb';"

echo '==== BATCH 1 CSV HEADER ===='
docker compose exec -T app php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$batch = App\Models\ImportBatch::withoutGlobalScopes()->find(1);
$path = $batch->source_storage_path;
$full = is_readable($path) ? $path : Illuminate\Support\Facades\Storage::disk("local")->path($path);
$fh = fopen($full, "r");
$header = fgetcsv($fh);
echo implode("|", $header), PHP_EOL;
'
