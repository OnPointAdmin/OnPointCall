<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo App\Models\User::withoutGlobalScopes()
    ->get(['id', 'name', 'email', 'role', 'active', 'company_id'])
    ->toJson(JSON_PRETTY_PRINT).PHP_EOL;
