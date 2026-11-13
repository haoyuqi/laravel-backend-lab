<?php

namespace Tests\Feature;

use Closure;
use Illuminate\Console\Scheduling\Schedule as Scheduler;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class SharedServiceConfigurationTest extends TestCase
{
    public function test_existing_connection_defaults_are_preserved(): void
    {
        $this->withEnvironment([], function (): void {
            $database = require config_path('database.php');
            $queue = require config_path('queue.php');
            $cache = require config_path('cache.php');
            $backup = require config_path('backup.php');

            $this->assertSame(0, $database['redis']['default']['database']);
            $this->assertSame(1, $database['redis']['cache']['database']);
            $this->assertSame(3, $database['redis']['queue']['database']);
            $this->assertSame('default', $queue['connections']['redis']['connection']);
            $this->assertSame('cache', $cache['stores']['redis']['connection']);
            $this->assertTrue($backup['schedule']['enabled']);
        });
    }

    public function test_acl_credentials_and_database_assignments_are_configurable(): void
    {
        $this->withEnvironment([
            'REDIS_USERNAME' => 'backend_lab',
            'REDIS_PASSWORD' => 'test-only-credential',
            'REDIS_DB' => '1',
            'REDIS_CACHE_DB' => '2',
            'REDIS_QUEUE_DB' => '1',
            'REDIS_QUEUE_CONNECTION' => 'queue',
            'REDIS_CACHE_CONNECTION' => 'cache',
            'DB_SSLMODE' => 'verify-full',
        ], function (): void {
            $database = require config_path('database.php');
            $queue = require config_path('queue.php');
            $cache = require config_path('cache.php');

            foreach (['default' => '1', 'cache' => '2', 'queue' => '1'] as $name => $db) {
                $this->assertSame('backend_lab', $database['redis'][$name]['username']);
                $this->assertSame('test-only-credential', $database['redis'][$name]['password']);
                $this->assertSame($db, $database['redis'][$name]['database']);
            }

            $this->assertSame('queue', $queue['connections']['redis']['connection']);
            $this->assertSame('cache', $cache['stores']['redis']['connection']);
            $this->assertSame('verify-full', $database['connections']['pgsql']['sslmode']);
        });
    }

    public function test_per_connection_endpoint_overrides_do_not_change_other_connections(): void
    {
        $this->withEnvironment([
            'REDIS_HOST' => 'shared.internal',
            'REDIS_USERNAME' => 'common',
            'REDIS_PASSWORD' => 'test-common',
            'REDIS_PORT' => '6379',
            'REDIS_CACHE_HOST' => 'cache.internal',
            'REDIS_CACHE_USERNAME' => 'cache_app',
            'REDIS_CACHE_PASSWORD' => 'test-cache',
            'REDIS_CACHE_PORT' => '6380',
            'REDIS_QUEUE_HOST' => 'queue.internal',
            'REDIS_QUEUE_USERNAME' => 'queue_app',
            'REDIS_QUEUE_PASSWORD' => 'test-queue',
            'REDIS_QUEUE_PORT' => '6381',
        ], function (): void {
            $redis = (require config_path('database.php'))['redis'];

            foreach (['default' => ['shared.internal', 'common', 'test-common', '6379'],
                'cache' => ['cache.internal', 'cache_app', 'test-cache', '6380'],
                'queue' => ['queue.internal', 'queue_app', 'test-queue', '6381']] as $name => $expected) {
                $this->assertSame($expected, array_map(fn ($key) => $redis[$name][$key], ['host', 'username', 'password', 'port']));
            }
        });
    }

    public function test_laravel_url_precedence_and_per_connection_urls_are_preserved(): void
    {
        $this->withEnvironment([
            'REDIS_URL' => 'redis://url_user:test-url@shared.internal:6380',
            'REDIS_DB' => '1',
            'REDIS_CACHE_DB' => '2',
            'REDIS_QUEUE_DB' => '3',
            'REDIS_QUEUE_URL' => 'redis://queue_user:test-queue@queue.internal:6381/7',
        ], function (): void {
            $redis = (require config_path('database.php'))['redis'];
            $parser = new ConfigurationUrlParser;
            $default = $parser->parseConfiguration($redis['default']);
            $cache = $parser->parseConfiguration($redis['cache']);
            $queue = $parser->parseConfiguration($redis['queue']);

            $this->assertSame('url_user', $default['username']);
            $this->assertSame('test-url', $default['password']);
            $this->assertSame('1', $default['database']);
            $this->assertSame('2', $cache['database']);
            $this->assertSame('queue_user', $queue['username']);
            $this->assertSame('queue.internal', $queue['host']);
            $this->assertSame('7', $queue['database']);
        });
    }

    public function test_url_database_and_query_options_keep_laravel_precedence(): void
    {
        $this->withEnvironment([
            'REDIS_URL' => 'redis://shared.internal:6379/4?database=5',
            'REDIS_DB' => '1',
            'REDIS_CACHE_DB' => '2',
            'REDIS_QUEUE_DB' => '3',
        ], function (): void {
            $redis = (require config_path('database.php'))['redis'];
            $parser = new ConfigurationUrlParser;

            foreach (['default', 'cache', 'queue'] as $name) {
                $this->assertSame(5, $parser->parseConfiguration($redis[$name])['database']);
            }
        });
    }

    public function test_cache_and_queue_connections_accept_existing_laravel_connection_names(): void
    {
        $this->withEnvironment([
            'REDIS_CACHE_CONNECTION' => 'default',
            'REDIS_QUEUE_CONNECTION' => 'cache',
        ], function (): void {
            $cache = require config_path('cache.php');
            $queue = require config_path('queue.php');

            $this->assertSame('default', $cache['stores']['redis']['connection']);
            $this->assertSame('cache', $queue['connections']['redis']['connection']);
        });
    }

    public function test_backup_schedule_can_be_disabled_without_removing_other_tasks(): void
    {
        $this->withEnvironment(['BACKUP_SCHEDULE_ENABLED' => 'false'], function (): void {
            $backup = require config_path('backup.php');
            $this->assertFalse($backup['schedule']['enabled']);
            $commands = $this->scheduledCommands($backup['schedule']['enabled']);
            $this->assertCount(0, array_filter($commands, fn ($command) => str_contains($command, 'backup:')));
            $this->assertTrue((bool) array_filter($commands, fn ($command) => str_contains($command, 'save:visits-count')));
        });
    }

    public function test_backup_schedule_remains_enabled_by_default(): void
    {
        $commands = $this->scheduledCommands(true);
        foreach (['backup:run', 'backup:clean', 'backup:monitor'] as $command) {
            $this->assertCount(1, array_filter($commands, fn ($scheduled) => str_contains($scheduled, $command)));
        }
    }

    private function scheduledCommands(bool $enabled): array
    {
        $original = Schedule::getFacadeRoot();
        $previous = config('backup.schedule.enabled');
        $schedule = new Scheduler;
        Schedule::swap($schedule);
        config(['backup.schedule.enabled' => $enabled]);

        try {
            require base_path('routes/console.php');

            return array_map(fn ($event) => $event->command ?? '', $schedule->events());
        } finally {
            Schedule::swap($original);
            config(['backup.schedule.enabled' => $previous]);
        }
    }

    private function withEnvironment(array $values, Closure $callback): void
    {
        $names = ['REDIS_URL', 'REDIS_HOST', 'REDIS_PORT', 'REDIS_USERNAME', 'REDIS_PASSWORD',
            'REDIS_DB', 'REDIS_CACHE_DB', 'REDIS_QUEUE_DB', 'REDIS_QUEUE_CONNECTION',
            'REDIS_CACHE_CONNECTION', 'BACKUP_SCHEDULE_ENABLED', 'DB_SSLMODE'];
        foreach (['CACHE', 'QUEUE'] as $name) {
            foreach (['URL', 'HOST', 'PORT', 'USERNAME', 'PASSWORD'] as $field) {
                $names[] = "REDIS_{$name}_{$field}";
            }
        }

        $previousEnv = $_ENV;
        $previousServer = $_SERVER;
        try {
            foreach ($names as $name) {
                unset($_ENV[$name], $_SERVER[$name]);
            }
            foreach ($values as $name => $value) {
                $_ENV[$name] = $_SERVER[$name] = $value;
            }
            $callback();
        } finally {
            $_ENV = $previousEnv;
            $_SERVER = $previousServer;
        }
    }
}
