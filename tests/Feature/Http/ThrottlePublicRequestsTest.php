<?php

namespace Tests\Feature\Http;

use App\Models\Visitor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class ThrottlePublicRequestsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Keep visitor recording local: the middleware and job must still run,
        // but an existing visitor avoids a GeoIP provider request.
        Visitor::create(['ip' => '127.0.0.1', 'city' => 'Localhost']);

        Redis::shouldReceive('sadd')->andReturn(1);
        Redis::shouldReceive('incr')->andReturn(1);
        Redis::shouldReceive('expireat')->andReturn(true);
    }

    public function test_public_pages_allow_the_limit_and_reject_the_next_request(): void
    {
        for ($request = 0; $request < 60; $request++) {
            $this->get('/')->assertOk();
        }

        $this->get('/')
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_health_endpoints_are_not_rate_limited(): void
    {
        Redis::shouldReceive('ping')->andReturn(true);

        for ($request = 0; $request < 60; $request++) {
            $this->get('/')->assertOk();
        }

        $this->get('/health/ready')->assertOk();
        $this->get('/up')->assertOk();
    }

    public function test_rate_limiter_storage_failure_fails_open(): void
    {
        Log::spy();
        RateLimiter::shouldReceive('tooManyAttempts')->once()->andThrow(new RuntimeException('cache unavailable'));

        $this->get('/')->assertOk();

        Log::shouldHaveReceived('warning')
            ->with('public rate limiter failure', \Mockery::on(
                fn (array $context): bool => $context['ip'] === '127.0.0.1'
                    && $context['error'] === 'cache unavailable',
            ))
            ->once();
    }
}
