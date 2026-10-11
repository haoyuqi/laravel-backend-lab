<?php

namespace Tests\Feature\Service;

use App\Models\AdminOperationLog;
use App\Models\BlackList;
use App\Models\User;
use App\Service\AdminBlackListOperations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

// Bypass the wrapping RefreshDatabase transaction: it changes afterCommit timing.
class AdminOperationCommitTest extends TestCase
{
    private array $cached = [];

    private int $invalidations = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        Redis::shouldReceive('hdel')->andReturnUsing(function ($key, $ip): int {
            $this->invalidations++;
            unset($this->cached[$ip]);

            return 1;
        });
    }

    public function refreshDatabase(): void
    {
        // Keep the shared guard: only :memory: SQLite or a distinct *_test database.
        $this->beforeRefreshingDatabase();
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        // Subsequent transaction-based tests must rebuild their own fixtures.
        RefreshDatabaseState::$migrated = false;
        RefreshDatabaseState::$inMemoryConnections = [];
    }

    #[DataProvider('operations')]
    public function test_refilled_cache_is_cleared_only_after_outermost_commit(string $operation): void
    {
        $record = $this->fixture($operation);
        DB::beginTransaction();
        $this->mutate($operation, $record);
        $preCommitInvalidations = $this->invalidations;
        // A concurrent cache-aside request can refill old DB state before commit.
        $this->cached = ['192.0.2.20' => 'stale', '192.0.2.21' => 'stale', 'unrelated' => 'keep'];
        DB::commit();

        $this->assertGreaterThan($preCommitInvalidations, $this->invalidations);
        $this->assertArrayNotHasKey('192.0.2.20', $this->cached);
        if ($operation === 'update') {
            $this->assertArrayNotHasKey('192.0.2.21', $this->cached);
        }
        $this->assertSame('keep', $this->cached['unrelated']);
        $this->assertDatabaseCount('admin_operation_logs', 1);
    }

    public function test_outer_rollback_discards_log_business_write_and_commit_callback(): void
    {
        DB::beginTransaction();
        app(AdminBlackListOperations::class)->create(['ip' => '192.0.2.20']);
        $count = $this->invalidations;
        $this->cached['192.0.2.20'] = '0';
        DB::rollBack();
        $this->assertSame($count, $this->invalidations);
        $this->assertSame('0', $this->cached['192.0.2.20']);
        $this->assertDatabaseCount('black_lists', 0);
        $this->assertDatabaseCount('admin_operation_logs', 0);
        // A later successful transaction must not execute the discarded callback.
        DB::transaction(fn () => null);
        $this->assertSame($count, $this->invalidations);
    }

    public function test_audit_failure_does_not_schedule_a_commit_callback(): void
    {
        Event::listen('eloquent.creating: '.AdminOperationLog::class, fn () => throw new RuntimeException('Audit failed'));
        try {
            app(AdminBlackListOperations::class)->create(['ip' => '192.0.2.20']);
            $this->fail('Expected failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit failed', $exception->getMessage());
        }
        $this->assertSame(0, $this->invalidations); // Audit fails before saved observer, no commit callback.
        DB::transaction(fn () => null);
        $this->assertSame(0, $this->invalidations);
        $this->assertDatabaseCount('black_lists', 0);
        $this->assertDatabaseCount('admin_operation_logs', 0);
    }

    public function test_migration_can_round_trip_without_touching_blacklist_records(): void
    {
        BlackList::create(['ip' => '192.0.2.22']);
        $migration = require database_path('migrations/2026_10_11_000000_create_admin_operation_logs_table.php');
        $migration->down();
        $migration->up();
        $this->assertDatabaseCount('black_lists', 1);
        $this->assertDatabaseCount('admin_operation_logs', 0);
    }

    public static function operations(): array
    {
        return [['create'], ['update'], ['delete'], ['restore']];
    }

    private function fixture(string $operation): ?BlackList
    {
        if ($operation === 'create') {
            return null;
        }
        $record = BlackList::create(['ip' => '192.0.2.20']);
        if ($operation === 'restore') {
            $record->delete();
        }

        return $record;
    }

    private function mutate(string $operation, ?BlackList $record): void
    {
        $service = app(AdminBlackListOperations::class);
        match ($operation) {
            'create' => $service->create(['ip' => '192.0.2.20']),
            'update' => $service->update($record, ['ip' => '192.0.2.21']),
            'delete' => $service->delete($record),
            'restore' => $service->addIp('192.0.2.20'),
        };
    }
}
