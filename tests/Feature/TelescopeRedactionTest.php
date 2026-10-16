<?php

namespace Tests\Feature;

use App\Providers\TelescopeServiceProvider;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\Watchers\RequestWatcher;
use Tests\TestCase;

class TelescopeRedactionTest extends TestCase
{
    public function test_livewire_credentials_and_snapshots_are_redacted_even_locally(): void
    {
        $this->app->instance('env', 'local');
        (new TelescopeServiceProvider($this->app))->register();
        $watcher = new class extends RequestWatcher
        {
            public function redact(array $input): array
            {
                return $this->payload($input);
            }
        };
        $input = ['components' => [[
            'snapshot' => json_encode(['data' => ['password' => 'test-only-value']]),
            'updates' => ['data.password' => 'test-only-value'],
        ]], '_token' => 'test-token', 'password' => 'test-only-value'];

        $redacted = $watcher->redact($input);
        $this->assertSame('********', $redacted['components']);
        $this->assertSame('********', $redacted['password']);
        $this->assertSame('********', $redacted['_token']);
        $this->assertContains('components', Telescope::$hiddenResponseParameters);
    }
}
