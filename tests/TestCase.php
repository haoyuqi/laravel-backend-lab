<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PDO;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase;

    /**
     * Ensure the configured real (MySQL/PostgreSQL) testing database exists
     * before `migrate:fresh` runs. SQLite (including `:memory:`) needs no
     * provisioning, which is what keeps a fresh checkout zero-config.
     */
    protected function beforeRefreshingDatabase(): void
    {
        $config = config('database.connections.testing');
        $driver = $config['driver'] ?? 'sqlite';

        if ($driver === 'sqlite') {
            return;
        }

        $this->createDatabaseIfMissing($config);
    }

    /**
     * Create the configured testing database on the server when it is missing.
     * Driver-specific because PostgreSQL does not support `CREATE DATABASE IF NOT EXISTS`.
     *
     * @throws \RuntimeException when TEST_DB_DATABASE is absent for a real driver
     */
    protected function createDatabaseIfMissing(array $config): void
    {
        $database = $config['database'] ?? null;

        if (! $database || $database === ':memory:') {
            throw new \RuntimeException(
                'TEST_DB_DATABASE must be set when TEST_DB_CONNECTION is "'.$config['driver'].'".'
            );
        }

        $pdo = new PDO(
            $this->serverDsn($config),
            $config['username'] ?? 'forge',
            $config['password'] ?? '',
        );

        if ($config['driver'] === 'mysql') {
            $pdo->exec(sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                $database
            ));

            return;
        }

        if ($config['driver'] === 'pgsql') {
            $exists = $pdo->query(
                'SELECT 1 FROM pg_database WHERE datname = '.$pdo->quote($database)
            )->fetchColumn();

            if ($exists === false) {
                $pdo->exec(sprintf('CREATE DATABASE "%s"', $database));
            }

            return;
        }

        throw new \RuntimeException('Unsupported TEST_DB_CONNECTION driver "'.$config['driver'].'".');
    }

    /**
     * Build a server-level DSN (no database selected) for the real drivers.
     */
    protected function serverDsn(array $config): string
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? (($config['driver'] ?? '') === 'pgsql' ? 5432 : 3306);

        return match ($config['driver']) {
            'mysql' => sprintf('mysql:host=%s;port=%d', $host, $port),
            'pgsql' => sprintf('pgsql:host=%s;port=%d;dbname=postgres', $host, $port),
            default => throw new \RuntimeException('Unsupported driver "'.($config['driver'] ?? 'null').'".'),
        };
    }
}
