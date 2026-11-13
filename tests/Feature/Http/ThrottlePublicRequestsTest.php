<?php

namespace Tests\Feature\Http;

use App\Jobs\RecordVisitors as RecordVisitorsJob;
use App\Models\Visitor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class ThrottlePublicRequestsTest extends TestCase
{
    private object $redisManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->redisManager = app('redis');

        // Keep visitor recording local: the middleware and job must still run,
        // but an existing visitor avoids a GeoIP provider request.
        Visitor::create(['ip' => '127.0.0.1', 'city' => 'Localhost']);
    }

    public function test_public_pages_allow_the_limit_and_reject_the_next_request(): void
    {
        Queue::fake();

        Redis::shouldReceive('sadd')->times(60)->andReturn(1);
        Redis::shouldReceive('incr')->times(60)->andReturn(1);
        Redis::shouldReceive('expireat')->times(120)->andReturn(true);

        for ($request = 0; $request < 60; $request++) {
            $this->get('/')->assertOk();
        }

        Queue::assertPushed(RecordVisitorsJob::class, 60);

        Queue::fake();

        $this->get('/')
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        Queue::assertNothingPushed();
    }

    public function test_test_route_is_also_throttled(): void
    {
        Queue::fake();

        Redis::shouldReceive('sadd')->times(60)->andReturn(1);
        Redis::shouldReceive('incr')->times(60)->andReturn(1);
        Redis::shouldReceive('expireat')->times(120)->andReturn(true);

        for ($request = 0; $request < 60; $request++) {
            $this->get('/test')->assertStatus(403);
        }

        Queue::assertPushed(RecordVisitorsJob::class, 60);

        Queue::fake();

        $this->get('/test')
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        Queue::assertNothingPushed();
    }

    public function test_ipv6_addresses_are_canonicalized_before_rate_limiting(): void
    {
        Queue::fake();

        for ($attempt = 0; $attempt < 60; $attempt++) {
            RateLimiter::hit('public-web:2001:db8::1', 60);
        }

        Redis::shouldReceive('sadd')->never();
        Redis::shouldReceive('incr')->never();

        $this->withServerVariables(['REMOTE_ADDR' => '2001:DB8::1'])
            ->get('/')
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        Queue::assertNothingPushed();
    }

    public function test_health_endpoints_are_not_rate_limited(): void
    {
        Redis::shouldReceive('sadd')->times(60)->andReturn(1);
        Redis::shouldReceive('incr')->times(60)->andReturn(1);
        Redis::shouldReceive('expireat')->times(120)->andReturn(true);
        Redis::shouldReceive('ping')->once()->andReturn(true);

        for ($request = 0; $request < 60; $request++) {
            $this->get('/')->assertOk();
        }

        $this->get('/health/ready')->assertOk();
        $this->get('/up')->assertOk();
    }

    public function test_rate_limiter_storage_failure_fails_open(): void
    {
        Redis::shouldReceive('sadd')->once()->andReturn(1);
        Redis::shouldReceive('incr')->once()->andReturn(1);
        Redis::shouldReceive('expireat')->twice()->andReturn(true);

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

    protected function tearDown(): void
    {
        try {
            \Mockery::close();
        } finally {
            Redis::clearResolvedInstance('redis');
            $this->app->instance('redis', $this->redisManager);
            parent::tearDown();
        }
    }
}
