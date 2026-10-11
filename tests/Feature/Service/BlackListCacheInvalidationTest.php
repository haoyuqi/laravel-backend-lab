<?php

namespace Tests\Feature\Service;

use App\Filament\Resources\BlackListResource\Pages\CreateBlackList;
use App\Filament\Resources\BlackListResource\Pages\EditBlackList;
use App\Filament\Resources\BlackListResource\Pages\ListBlackLists;
use App\Filament\Resources\VisitorResource\Pages\ListVisitors;
use App\Models\BlackList;
use App\Models\User;
use App\Models\Visitor;
use App\Service\BlackListService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Livewire\Livewire;
use Tests\TestCase;

class BlackListCacheInvalidationTest extends TestCase
{
    private object $redisManager;

    private object $redisConnection;

    private User $admin;

    private string $key;

    private string $otherKey;

    protected function setUp(): void
    {
        parent::setUp();

        $prefix = 'test_blacklist_'.bin2hex(random_bytes(8)).'_';
        config(['database.redis.options.prefix' => $prefix]);
        $this->redisManager = app('redis');
        $this->redisConnection = $this->redisManager->connection();
        $this->key = app(BlackListService::class)->cacheKey();
        $this->otherKey = 'test_blacklist_unrelated_'.bin2hex(random_bytes(8));
        $this->admin = User::factory()->create();
    }

    public function test_create_invalidates_cached_negative_and_blocks_next_request(): void
    {
        $ip = '198.51.100.101';
        $this->seedCacheField($ip, '0');

        Livewire::actingAs($this->admin)
            ->test(CreateBlackList::class)
            ->fillForm(['ip' => $ip])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertMissing($ip);
        $this->assertTrue(app(BlackListService::class)->checkIp($ip, '/'));
    }

    public function test_edit_ip_invalidates_old_and_new_cached_decisions(): void
    {
        $oldIp = '198.51.100.102';
        $newIp = '198.51.100.103';
        $record = BlackList::create(['ip' => $oldIp]);
        $this->seedCacheField($oldIp, '1');
        $this->seedCacheField($newIp, '0');

        Livewire::actingAs($this->admin)
            ->test(EditBlackList::class, ['record' => $record->getKey()])
            ->fillForm(['ip' => $newIp])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertMissing($oldIp);
        $this->assertMissing($newIp);
        $this->assertFalse(app(BlackListService::class)->checkIp($oldIp, '/'));
        $this->assertTrue(app(BlackListService::class)->checkIp($newIp, '/'));
    }

    public function test_delete_via_table_action_invalidates_and_allows_next_request(): void
    {
        $ip = '198.51.100.104';
        $record = BlackList::create(['ip' => $ip]);
        $this->seedCacheField($ip, '1');

        Livewire::actingAs($this->admin)
            ->test(ListBlackLists::class)
            ->searchTable($ip)
            ->callTableAction('delete', $record);

        $this->assertSoftDeleted('black_lists', ['id' => $record->id]);
        $this->assertMissing($ip);
        $this->assertFalse(app(BlackListService::class)->checkIp($ip, '/'));
    }

    public function test_delete_via_edit_page_header_action_invalidates(): void
    {
        $ip = '198.51.100.105';
        $record = BlackList::create(['ip' => $ip]);
        $this->seedCacheField($ip, '1');

        Livewire::actingAs($this->admin)
            ->test(EditBlackList::class, ['record' => $record->getKey()])
            ->callAction('delete');

        $this->assertSoftDeleted('black_lists', ['id' => $record->id]);
        $this->assertMissing($ip);
        $this->assertFalse(app(BlackListService::class)->checkIp($ip, '/'));
    }

    public function test_bulk_delete_invalidates_each_ip(): void
    {
        $ips = ['198.51.100.106', '198.51.100.107'];
        $records = collect($ips)->map(fn (string $ip) => BlackList::create(['ip' => $ip]));
        foreach ($ips as $ip) {
            $this->seedCacheField($ip, '1');
        }

        Livewire::actingAs($this->admin)
            ->test(ListBlackLists::class)
            ->callTableBulkAction('delete', $records->all());

        foreach ($records as $record) {
            $this->assertSoftDeleted('black_lists', ['id' => $record->id]);
        }
        foreach ($ips as $ip) {
            $this->assertMissing($ip);
            $this->assertFalse(app(BlackListService::class)->checkIp($ip, '/'));
        }
    }

    public function test_restore_via_visitor_bulk_action_invalidates(): void
    {
        $ip = '198.51.100.108';
        $visitor = Visitor::create(['ip' => $ip, 'city' => 'Test']);
        $record = BlackList::create(['ip' => $ip]);
        $record->delete();
        $this->seedCacheField($ip, '0');

        Livewire::actingAs($this->admin)
            ->test(ListVisitors::class)
            ->callTableBulkAction('add_to_black_list', [$visitor])
            ->assertHasNoTableBulkActionErrors();

        $this->assertDatabaseHas('black_lists', ['ip' => $ip, 'deleted_at' => null]);
        $this->assertMissing($ip);
        $this->assertTrue(app(BlackListService::class)->checkIp($ip, '/'));
    }

    public function test_visitor_bulk_action_create_invalidates_cached_negative(): void
    {
        $ip = '198.51.100.109';
        $visitor = Visitor::create(['ip' => $ip, 'city' => 'Test']);
        $this->seedCacheField($ip, '0');

        Livewire::actingAs($this->admin)
            ->test(ListVisitors::class)
            ->callTableBulkAction('add_to_black_list', [$visitor])
            ->assertHasNoTableBulkActionErrors();

        $this->assertDatabaseHas('black_lists', ['ip' => $ip, 'deleted_at' => null]);
        $this->assertMissing($ip);
        $this->assertTrue(app(BlackListService::class)->checkIp($ip, '/'));
    }

    public function test_invalidation_failure_does_not_prevent_admin_database_commit_and_warns(): void
    {
        $ip = '198.51.100.110';
        $this->seedCacheField($ip, '0');
        Log::shouldReceive('warning')
            ->once()
            ->with('blacklist cache invalidation failure', \Mockery::on(fn (array $context): bool => $context['ip'] === $ip && $context['error'] === 'redis down'));
        // Observer invalidation plus the audited operation's afterCommit retry.
        Redis::shouldReceive('hdel')->twice()->andThrow(new \RuntimeException('redis down'));

        Livewire::actingAs($this->admin)
            ->test(CreateBlackList::class)
            ->fillForm(['ip' => $ip])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('black_lists', ['ip' => $ip, 'deleted_at' => null]);
    }

    public function test_redis_read_failure_falls_back_to_database_and_warns(): void
    {
        $ip = '198.51.100.111';
        BlackList::create(['ip' => $ip]);
        Log::shouldReceive('warning')
            ->once()
            ->with('blacklist cache read failure', \Mockery::on(fn (array $context): bool => $context['ip'] === $ip && $context['error'] === 'redis read failed'));
        Redis::shouldReceive('hget')->once()->andThrow(new \RuntimeException('redis read failed'));
        Redis::shouldReceive('hset')->once();
        Redis::shouldReceive('expire')->once();

        $this->assertTrue(app(BlackListService::class)->checkIp($ip, '/'));
    }

    public function test_redis_expiry_failure_logs_write_warning_and_returns_database_decision(): void
    {
        $ip = '198.51.100.118';
        BlackList::create(['ip' => $ip]);
        Log::shouldReceive('warning')
            ->once()
            ->with('blacklist cache write failure', \Mockery::on(fn (array $context): bool => $context['key'] === $this->key && $context['error'] === 'redis expiry failed'));
        Redis::shouldReceive('hget')->once()->andReturn(false);
        Redis::shouldReceive('hset')->once();
        Redis::shouldReceive('expire')->once()->andThrow(new \RuntimeException('redis expiry failed'));

        $this->assertTrue(app(BlackListService::class)->checkIp($ip, '/'));
    }

    public function test_redis_write_failure_preserves_database_decision_and_warns(): void
    {
        $ip = '198.51.100.112';
        BlackList::create(['ip' => $ip]);
        Log::shouldReceive('warning')
            ->once()
            ->with('blacklist cache write failure', \Mockery::on(fn (array $context): bool => $context['ip'] === $ip && $context['error'] === 'redis write failed'));
        Redis::shouldReceive('hget')->once()->andReturn(false);
        Redis::shouldReceive('hset')->once()->andThrow(new \RuntimeException('redis write failed'));

        $this->assertTrue(app(BlackListService::class)->checkIp($ip, '/'));
    }

    public function test_positive_and_negative_cache_entries_receive_a_48_hour_ttl(): void
    {
        $this->travelTo(now()->startOfSecond());
        $blacklistedIp = '198.51.100.113';
        $allowedIp = '198.51.100.114';
        BlackList::create(['ip' => $blacklistedIp]);
        $service = app(BlackListService::class);

        $this->assertTrue($service->checkIp($blacklistedIp, '/'));
        $this->assertFalse($service->checkIp($allowedIp, '/'));

        foreach ([$blacklistedIp => '1', $allowedIp => '0'] as $ip => $value) {
            $this->assertSame($value, $this->redisConnection->hget($this->key, $ip));
        }
        $ttl = $this->redisConnection->ttl($this->key);
        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(172800, $ttl);
    }

    public function test_invalidation_preserves_other_hash_fields_and_unrelated_keys(): void
    {
        $ip = '198.51.100.115';
        $otherIp = '198.51.100.116';
        $this->redisConnection->hset($this->key, $otherIp, '1');
        $this->redisConnection->set($this->otherKey, 'preserved');
        $this->seedCacheField($ip, '0');
        $record = BlackList::create(['ip' => $ip]);

        $this->assertDatabaseHas('black_lists', ['id' => $record->id]);
        $this->assertMissing($ip);
        $this->assertSame('1', $this->redisConnection->hget($this->key, $otherIp));
        $this->assertSame('preserved', $this->redisConnection->get($this->otherKey));
    }

    public function test_blacklist_log_parent_timestamp_touch_preserves_positive_cache(): void
    {
        $ip = '198.51.100.117';
        $record = BlackList::create(['ip' => $ip]);
        $this->seedCacheField($ip, '1');

        $this->assertTrue(app(BlackListService::class)->checkIp($ip, '/blocked'));

        $this->assertSame('1', $this->redisConnection->hget($this->key, $ip));
        $this->assertDatabaseHas('black_list_logs', ['black_list_id' => $record->id, 'url' => '/blocked']);
    }

    private function seedCacheField(string $ip, string $value): void
    {
        $this->redisConnection->hset($this->key, $ip, $value);
    }

    private function assertMissing(string $ip): void
    {
        $this->assertFalse($this->redisConnection->hget($this->key, $ip));
    }

    protected function tearDown(): void
    {
        try {
            \Mockery::close();
        } finally {
            Redis::clearResolvedInstance('redis');
            $this->app->instance('redis', $this->redisManager);

            try {
                $this->redisConnection->del($this->key, $this->otherKey);
            } finally {
                parent::tearDown();
            }
        }
    }
}
