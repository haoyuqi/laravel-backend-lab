<?php

namespace Tests\Unit\Service;

use App\Models\BlackList;
use App\Service\BlackListService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class BlackListServiceKeyTest extends TestCase
{
    public function test_cache_key_format(): void
    {
        $service = $this->app->make(BlackListService::class);

        $this->assertSame('black_list_'.now()->toDateString(), $service->cacheKey());
    }

    public function test_cache_key_with_custom_date(): void
    {
        $service = $this->app->make(BlackListService::class);

        $this->assertSame(
            'black_list_2026-01-15',
            $service->cacheKey(Carbon::parse('2026-01-15'))
        );
    }

    public function test_forget_ip_calls_hdel(): void
    {
        Redis::shouldReceive('hdel')
            ->once()
            ->with('black_list_'.now()->toDateString(), '1.2.3.4');

        $service = $this->app->make(BlackListService::class);

        $service->forgetIp('1.2.3.4');
    }

    public function test_forget_ip_logs_warning_on_redis_failure(): void
    {
        Redis::shouldReceive('hdel')->andThrow(new Exception('redis down'));
        Log::shouldReceive('warning')->once();

        $service = $this->app->make(BlackListService::class);

        $service->forgetIp('1.2.3.4');
    }

    public function test_check_ip_uses_database_without_redis_enforcement(): void
    {
        BlackList::withoutEvents(fn () => BlackList::create(['ip' => '8.8.4.4']));
        Redis::shouldReceive('hget')->never();
        Redis::shouldReceive('hset')->never();

        $this->assertTrue($this->app->make(BlackListService::class)->checkIp('8.8.4.4', '/'));
    }
}
