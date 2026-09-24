<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase;

    /**
     * Validate the target before RefreshDatabase can drop any tables.
     * Laravel's migrate command creates a missing MySQL/PostgreSQL database.
     */
    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'testing') {
            throw new RuntimeException('Tests must use the dedicated testing database connection.');
        }

        $config = config('database.connections.testing');
        $driver = $config['driver'] ?? 'sqlite';

        if ($driver === 'sqlite') {
            if (($config['database'] ?? null) !== ':memory:') {
                throw new RuntimeException('SQLite tests must use the in-memory database.');
            }

            return;
        }

        if (! in_array($driver, ['mysql', 'mariadb', 'pgsql'], true)) {
            throw new RuntimeException('Unsupported TEST_DB_CONNECTION driver "'.$driver.'".');
        }

        $database = $config['database'] ?? null;

        if (! is_string($database) || preg_match('/\A[a-zA-Z][a-zA-Z0-9_]*_test\z/', $database) !== 1) {
            throw new RuntimeException(
                'TEST_DB_DATABASE must be a dedicated alphanumeric database name ending in "_test" when using '.$driver.'.'
            );
        }

        foreach (config('database.connections') as $name => $connection) {
            if ($name !== 'testing' && ($connection['driver'] ?? null) === $driver &&
                ($connection['database'] ?? null) === $database) {
                throw new RuntimeException('TEST_DB_DATABASE must not match another '.$driver.' connection.');
            }
        }
    }
}
