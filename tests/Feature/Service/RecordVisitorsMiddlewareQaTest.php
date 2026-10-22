<?php

namespace Tests\Feature\Service;

use App\Http\Middleware\RecordVisitors;
use App\Models\BlackList;
use App\Service\BlackListService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RecordVisitorsMiddlewareQaTest extends TestCase
{
    private object $redisManager;

    private object $redisConnection;

    private string $key;

    private string $ip = '203.0.113.240';

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.redis.options.prefix' => 'middleware_qa_'.bin2hex(random_bytes(8)).'_']);
        $this->redisManager = app('redis');
        $this->redisConnection = $this->redisManager->connection();
        $this->key = app(BlackListService::class)->cacheKey();
    }

    public function test_middleware_blocks_created_ip_then_allows_after_delete(): void
    {
        $this->redisConnection->hset($this->key, $this->ip, '0');
        BlackList::create(['ip' => $this->ip]);

        try {
            $this->middleware()->handle($this->request(), fn () => new Response('continued', 200));
            $this->fail('Expected the middleware to abort a blacklisted request.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        BlackList::where('ip', $this->ip)->firstOrFail()->delete();
        $response = $this->middleware()->handle($this->request(), fn () => new Response('continued', 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('continued', $response->getContent());
    }

    public function test_dispatch_failure_does_not_fail_request_and_logs_warning(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('visitor recording dispatch failure', \Mockery::on(fn (array $context): bool => $context['ip'] === $this->ip &&
                $context['url'] === '/middleware-qa' &&
                $context['exception_class'] === \RuntimeException::class
            ));

        $queueMock = \Mockery::mock(\Illuminate\Contracts\Queue\Queue::class);
        $queueMock->shouldReceive('push')
            ->once()
            ->andThrow(new \RuntimeException('Redis queue failure'));

        Queue::shouldReceive('connection')
            ->andReturn($queueMock);

        $response = $this->middleware()->handle($this->request(), fn () => new Response('continued', 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('continued', $response->getContent());
    }

    private function middleware(): RecordVisitors
    {
        return app(RecordVisitors::class);
    }

    public function test_database_blacklist_fallback_still_blocks_when_redis_queue_is_down(): void
    {
        BlackList::withoutEvents(fn () => BlackList::create(['ip' => $this->ip]));
        $this->redisConnection->hset($this->key, $this->ip, '0');

        $this->assertBlockedWithFailedLogQueue();
    }

    public function test_stale_positive_cache_does_not_override_database_allow(): void
    {
        $this->redisConnection->hset($this->key, $this->ip, '1');
        Queue::fake();

        $response = $this->middleware()->handle($this->request(), fn () => new Response('continued', 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('continued', $response->getContent());
    }

    public function test_production_request_without_a_client_ip_continues_without_dispatching_a_job(): void
    {
        $this->app->instance('env', 'production');
        Queue::fake();
        $request = Request::create('/no-client-ip');

        $response = $this->middleware()->handle($request, fn () => new Response('continued', 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('continued', $response->getContent());
        Queue::assertNothingPushed();
    }

    private function assertBlockedWithFailedLogQueue(): void
    {
        Log::shouldReceive('warning')->once()->with('blacklist logging dispatch failure', \Mockery::on(fn (array $context): bool => $context['black_list_id'] === BlackList::where('ip', $this->ip)->value('id') &&
            $context['url'] === '/middleware-qa' &&
            $context['exception_class'] === \RuntimeException::class &&
            ! array_key_exists('error', $context)
        ));

        $queue = \Mockery::mock(\Illuminate\Contracts\Queue\Queue::class);
        $queue->shouldReceive('push')->once()->andThrow(new \RuntimeException('Redis queue unavailable'));
        Queue::shouldReceive('connection')->andReturn($queue);

        try {
            $this->middleware()->handle($this->request(), fn () => new Response('continued', 200));
            $this->fail('Expected the blacklist to block the request.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    private function request(): Request
    {
        return Request::create('/middleware-qa?token=secret', 'GET', server: ['REMOTE_ADDR' => $this->ip]);
    }

    protected function tearDown(): void
    {
        try {
            Redis::clearResolvedInstance('redis');
            $this->app->instance('redis', $this->redisManager);
            $this->redisConnection->del($this->key);
        } finally {
            parent::tearDown();
        }
    }
}
