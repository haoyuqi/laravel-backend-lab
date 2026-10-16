<?php

namespace Tests\Feature\Service;

use App\Filament\Resources\BlackListResource\Pages\CreateBlackList;
use App\Filament\Resources\BlackListResource\Pages\EditBlackList;
use App\Filament\Resources\BlackListResource\Pages\ListBlackLists;
use App\Filament\Resources\VisitorResource\Pages\ListVisitors;
use App\Jobs\BlackListLog as BlackListLogJob;
use App\Models\BlackList;
use App\Models\User;
use App\Models\Visitor;
use App\Service\AdminBlackListOperations;
use App\Service\BlackListService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
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

    public function test_database_decision_wins_over_stale_redis_values_after_mutations(): void
    {
        $addedIp = '198.51.100.111';
        $removedIp = '198.51.100.112';
        BlackList::create(['ip' => $addedIp]);
        $removed = BlackList::create(['ip' => $removedIp]);
        $removed->delete();
        $this->seedCacheField($addedIp, '0');
        $this->seedCacheField($removedIp, '1');

        $this->assertTrue(app(BlackListService::class)->checkIp($addedIp, '/'));
        $this->assertFalse(app(BlackListService::class)->checkIp($removedIp, '/'));
    }

    public function test_ipv6_input_matches_legacy_expanded_and_deleted_records(): void
    {
        $expanded = '2001:0db8:0000:0000:0000:ff00:0042:8329';
        $compressed = '2001:db8::ff00:42:8329';
        $legacy = BlackList::withoutEvents(fn () => BlackList::create(['ip' => $expanded]));

        $this->assertTrue(app(BlackListService::class)->checkIp($compressed, '/'));
        $this->actingAs($this->admin);
        $canonical = app(AdminBlackListOperations::class)->create(['ip' => '2001:0db8:0000:0000:0000:0000:0000:0001']);
        $this->assertSame('2001:db8::1', $canonical->ip);

        $legacyDeleted = BlackList::findOrFail($legacy->id);
        $legacyDeleted->delete();
        $this->assertFalse(app(BlackListService::class)->checkIp($compressed, '/'));

        $restored = app(AdminBlackListOperations::class)->addIp($compressed);
        $this->assertSame($legacyDeleted->id, $restored->id);
        $this->assertFalse($restored->trashed());
        $this->assertTrue(app(BlackListService::class)->checkIp($compressed, '/'));

        $newAddress = app(AdminBlackListOperations::class)->addIp('2001:0db8:0000:0000:0000:0000:0000:0002');
        $this->assertSame('2001:db8::2', $newAddress->ip);
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
        Queue::fake();

        $this->assertTrue(app(BlackListService::class)->checkIp($ip, '/blocked'));
        Queue::assertPushed(BlackListLogJob::class);
        (new BlackListLogJob($record->id, '/blocked'))->handle();

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
