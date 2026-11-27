<?php

namespace Tests\Feature;

use Tests\TestCase;

class ConfigurationTest extends TestCase
{
    public function test_configurations_match_environment_defaults(): void
    {
        $this->assertEquals('log', config('broadcasting.default'));
        $this->assertEquals('array', config('cache.default'));
        $this->assertEquals('array', config('mail.default'));
        $this->assertEquals('sync', config('queue.default'));
    }

    public function test_database_routes_to_dedicated_testing_connection(): void
    {
        // PHPUnit pins the default connection to the dedicated `testing`
        // connection so the suite can never touch the primary dev database.
        $this->assertEquals('testing', config('database.default'));
        $this->assertEquals('testing', config('queue.failed.database'));
    }
}
