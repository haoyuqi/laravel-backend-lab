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
}
