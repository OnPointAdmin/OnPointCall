<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap. Runs before Laravel boots so Docker's DB_CONNECTION=pgsql
 * (and a leftover `php artisan optimize` config cache) cannot point tests at
 * the development Postgres database.
 */
$testingEnv = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
];

foreach ($testingEnv as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

putenv('DB_URL');
unset($_ENV['DB_URL'], $_SERVER['DB_URL']);

$cacheDir = dirname(__DIR__).DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache';

foreach (['config.php', 'routes-v7.php', 'events.php'] as $file) {
    $path = $cacheDir.DIRECTORY_SEPARATOR.$file;

    if (is_file($path)) {
        @unlink($path);
    }
}

require dirname(__DIR__).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
