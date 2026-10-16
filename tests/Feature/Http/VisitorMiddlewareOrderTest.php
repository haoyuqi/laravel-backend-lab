<?php

namespace Tests\Feature\Http;

use App\Http\Middleware\CountPvAndUv;
use App\Jobs\RecordVisitors;
use App\Service\BlackListService;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VisitorMiddlewareOrderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(CountPvAndUv::class);
        Queue::fake();
        TrustProxies::flushState();
    }

    public function test_blacklist_uses_trusted_client_ip_and_sanitized_path(): void
    {
        config(['trustedproxy.proxies' => ['192.0.2.10']]);
        $this->mock(BlackListService::class)->shouldReceive('checkIp')
            ->once()->with('2001:db8::1', '/time')->andReturn(false);

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->withHeader('X-Forwarded-For', '2001:db8::1')
            ->get('/time?token=test-only-value')->assertOk();
        Queue::assertPushed(RecordVisitors::class, 1);
    }

    public function test_untrusted_client_cannot_spoof_forwarded_ip(): void
    {
        config(['trustedproxy.proxies' => ['192.0.2.10']]);
        $this->mock(BlackListService::class)->shouldReceive('checkIp')
            ->once()->with('192.0.2.11', '/time')->andReturn(false);

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11'])
            ->withHeader('X-Forwarded-For', '203.0.113.1')->get('/time')->assertOk();
    }

    public function test_maintenance_rejects_requests_before_tracking(): void
    {
        $this->mock(MaintenanceMode::class, function ($mock) {
            $mock->shouldReceive('active')->andReturn(true);
            $mock->shouldReceive('data')->andReturn(['status' => 503]);
        });
        $this->mock(BlackListService::class)->shouldNotReceive('checkIp');
        $this->get('/time')->assertStatus(503);
        Queue::assertNothingPushed();
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        parent::tearDown();
    }
}
