<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $this->forceTestingEnvironment();

        $app = parent::createApplication();

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.url', null);
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('queue.default', 'sync');

        // If anything still tries to use Postgres, fail closed instead of
        // migrate:fresh-ing the Docker dev database.
        $app['config']->set('database.connections.pgsql.host', '127.0.0.1');
        $app['config']->set('database.connections.pgsql.port', 1);
        $app['config']->set('database.connections.pgsql.database', 'phpunit_must_use_sqlite');
        $app['config']->set('database.connections.pgsql.username', 'phpunit');
        $app['config']->set('database.connections.pgsql.password', 'phpunit');

        if ($app->bound('db')) {
            foreach (array_keys($app['config']->get('database.connections')) as $name) {
                $app['db']->purge($name);
            }
        }

        $default = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$default}.database");

        if ($default !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "Tests must use sqlite :memory: only (got {$default} / {$database}). Refusing to run against the dev database."
            );
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function forceTestingEnvironment(): void
    {
        foreach ([
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        putenv('DB_URL');
        unset($_ENV['DB_URL'], $_SERVER['DB_URL']);
    }
}
