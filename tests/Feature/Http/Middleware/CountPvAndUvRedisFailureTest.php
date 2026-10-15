<?php

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\CountPvAndUv;
use App\Http\Middleware\RecordVisitors;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CountPvAndUvRedisFailureTest extends TestCase
{
    private object $redisManager;

    private object $redisConnection;

    private string $uvKey;

    private string $pvKey;

    protected function setUp(): void
    {
        parent::setUp();

        $prefix = 'test_count_pv_uv_'.bin2hex(random_bytes(8)).'_';
        config(['database.redis.options.prefix' => $prefix]);
        $this->redisManager = app('redis');
        $this->redisConnection = $this->redisManager->connection();

        $date = now()->toDateString();
        $this->uvKey = 'uv_set_'.$date;
        $this->pvKey = 'pv_count_'.$date;
    }

    public function test_redis_sadd_failure_does_not_fail_request_and_logs_warning(): void
    {
        $ip = '198.51.100.201';
        $request = Request::create('/test-pv-uv', 'GET', server: ['REMOTE_ADDR' => $ip]);

        Log::shouldReceive('warning')
            ->once()
            ->with('pv/uv count failure', \Mockery::on(fn (array $context): bool => $context['ip'] === $ip && str_contains($context['error'], 'Redis sadd failed')
            ));

        Redis::shouldReceive('sadd')
            ->once()
            ->andThrow(new \RuntimeException('Redis sadd failed'));
        Redis::shouldReceive('expireat')->twice()->andReturn(0);

        $middleware = new CountPvAndUv;
        $response = $middleware->handle($request, fn () => new Response('ok', 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    public function test_redis_incr_failure_does_not_fail_request_and_logs_warning(): void
    {
        $ip = '198.51.100.202';
        $request = Request::create('/test-pv-uv', 'GET', server: ['REMOTE_ADDR' => $ip]);

        Log::shouldReceive('warning')
            ->once()
            ->with('pv/uv count failure', \Mockery::on(fn (array $context): bool => $context['ip'] === $ip && str_contains($context['error'], 'Redis incr failed')
            ));

        Redis::shouldReceive('sadd')->once()->andReturn(1);
        Redis::shouldReceive('incr')
            ->once()
            ->andThrow(new \RuntimeException('Redis incr failed'));
        Redis::shouldReceive('expireat')->twice()->andReturn(1);

        $middleware = new CountPvAndUv;
        $response = $middleware->handle($request, fn () => new Response('ok', 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    public function test_http_request_returns_200_when_redis_fails(): void
    {
        Route::get('/_test-pv-uv-route', fn () => response('page content', 200))
            ->middleware(CountPvAndUv::class);

        Redis::shouldReceive('sadd')
            ->once()
            ->andThrow(new \RuntimeException('Connection refused'));
        Redis::shouldReceive('expireat')->twice()->andReturn(0);

        Log::shouldReceive('warning')
            ->once()
            ->with('pv/uv count failure', \Mockery::type('array'));

        $response = $this->withoutMiddleware([RecordVisitors::class])
            ->get('/_test-pv-uv-route');

        $response->assertStatus(200);
        $response->assertSee('page content');
    }

    public function test_healthy_redis_records_uv_and_pv(): void
    {
        $ip = '198.51.100.203';
        $request = Request::create('/test-pv-uv', 'GET', server: ['REMOTE_ADDR' => $ip]);

        $middleware = new CountPvAndUv;
        $response = $middleware->handle($request, fn () => new Response('ok', 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, (int) $this->redisConnection->sismember($this->uvKey, $ip));
        $this->assertSame(1, (int) $this->redisConnection->get($this->pvKey));
        $this->assertGreaterThan(0, $this->redisConnection->ttl($this->pvKey));
        $this->assertGreaterThan(0, $this->redisConnection->ttl($this->uvKey));
    }

    public function test_health_routes_bypass_pv_uv_counting(): void
    {
        $requestUp = Request::create('/up', 'GET');
        $requestHealthReady = Request::create('/health/ready', 'GET');

        Redis::shouldReceive('sadd')->never();
        Redis::shouldReceive('incr')->never();

        $middleware = new CountPvAndUv;
        $response1 = $middleware->handle($requestUp, fn () => new Response('ok', 200));
        $response2 = $middleware->handle($requestHealthReady, fn () => new Response('ok', 200));

        $this->assertSame(200, $response1->getStatusCode());
        $this->assertSame(200, $response2->getStatusCode());
    }

    public function test_expiration_failure_does_not_fail_the_page_request(): void
    {
        Redis::shouldReceive('sadd')->once()->andReturn(1);
        Redis::shouldReceive('incr')->once()->andReturn(1);
        Redis::shouldReceive('expireat')->once()->with($this->uvKey, \Mockery::type('int'))->andThrow(new \RuntimeException('Expiration failed'));
        Redis::shouldReceive('expireat')->once()->with($this->pvKey, \Mockery::type('int'))->andReturn(1);
        Log::shouldReceive('warning')->once()->with('pv/uv expiration failure', \Mockery::type('array'));
        $request = Request::create('/test-pv-uv', 'GET', server: ['REMOTE_ADDR' => '198.51.100.204']);
        $response = (new CountPvAndUv)->handle($request, fn () => new Response('ok', 200));
        $this->assertSame(200, $response->getStatusCode());
    }

    protected function tearDown(): void
    {
        try {
            \Mockery::close();
        } finally {
            Redis::clearResolvedInstance('redis');
            $this->app->instance('redis', $this->redisManager);

            try {
                $this->redisConnection->del($this->uvKey, $this->pvKey);
            } finally {
                parent::tearDown();
            }
        }
    }
}
