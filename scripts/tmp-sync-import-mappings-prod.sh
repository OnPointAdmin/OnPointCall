#!/bin/bash
set -euo pipefail
cd /opt/onpointcall

echo '==== BEFORE ===='
docker compose exec -T db psql -U onpoint -d onpoint_call -c 'SELECT id, company_id, name, lead_type, is_default FROM import_mappings ORDER BY id;'

echo '==== SEEDING ImportMappingSeeder for company_id=1 ===='
docker compose exec -T app php artisan db:seed --class=ImportMappingSeeder --force --no-interaction 2>&1 || true

# Seeder expects run($companyId); db:seed may not pass it. Prefer explicit tinker/php.
docker compose exec -T app php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
(new Database\Seeders\ImportMappingSeeder)->run(1);
echo "Seeded company 1\n";
foreach (App\Models\ImportMapping::withoutGlobalScopes()->orderBy("id")->get() as $m) {
    echo $m->id." | ".$m->name." | ".$m->lead_type." | default=".($m->is_default?"1":"0")." | keys=".implode(",", array_keys($m->column_map ?? []))."\n";
}
'

echo '==== AFTER ===='
docker compose exec -T db psql -U onpoint -d onpoint_call -c 'SELECT id, company_id, name, lead_type, is_default, column_map FROM import_mappings ORDER BY id;'
