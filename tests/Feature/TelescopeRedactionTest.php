<?php

namespace Tests\Feature;

use App\Providers\TelescopeServiceProvider;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\Watchers\RequestWatcher;
use Tests\TestCase;

class TelescopeRedactionTest extends TestCase
{
    public function test_telescope_records_local_entries_and_only_selected_production_entries(): void
    {
        $previousFilters = Telescope::$filterUsing;
        try {
            $this->app->instance('env', 'local');
            (new TelescopeServiceProvider($this->app))->register();
            $filter = end(Telescope::$filterUsing);
            $this->assertTrue($filter(\Mockery::mock(IncomingEntry::class)));

            $this->app->instance('env', 'production');
            (new TelescopeServiceProvider($this->app))->register();
            $filter = end(Telescope::$filterUsing);
            $methods = ['isReportableException', 'isFailedRequest', 'isFailedJob', 'isScheduledTask', 'hasMonitoredTag'];

            foreach ($methods as $index => $acceptedMethod) {
                $entry = \Mockery::mock(IncomingEntry::class);
                foreach ($methods as $methodIndex => $method) {
                    if ($methodIndex > $index) {
                        $entry->shouldNotReceive($method);
                    } else {
                        $entry->shouldReceive($method)->once()->andReturn($method === $acceptedMethod);
                    }
                }
                $this->assertTrue($filter($entry));
            }

            $entry = \Mockery::mock(IncomingEntry::class);
            foreach ($methods as $method) {
                $entry->shouldReceive($method)->once()->andReturnFalse();
            }
            $this->assertFalse($filter($entry));
        } finally {
            Telescope::$filterUsing = $previousFilters;
        }
    }

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
