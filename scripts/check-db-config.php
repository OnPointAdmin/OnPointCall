<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo 'default='.config('database.default').PHP_EOL;
echo 'sqlite_db='.config('database.connections.sqlite.database').PHP_EOL;
