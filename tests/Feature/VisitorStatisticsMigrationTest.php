<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VisitorStatisticsMigrationTest extends TestCase
{
    // MySQL schema changes commit implicitly; use a disposable test schema instead.
    protected $connectionsToTransact = [];

    private object $migration;

    protected function setUp(): void
    {
        RefreshDatabaseState::$migrated = false;
        RefreshDatabaseState::$inMemoryConnections = [];
        parent::setUp();
        $this->migration = require database_path('migrations/2026_10_02_000000_make_visitor_statistics_unique.php');
        $this->migration->down();
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;
        RefreshDatabaseState::$inMemoryConnections = [];
        parent::tearDown();
    }

    private function snapshot(int $count, string $updated, ?string $deleted = null, string $type = 'pv'): int
    {
        return DB::table('visitor_statistics')->insertGetId([
            'type' => $type, 'date' => '2026-09-30', 'count' => $count,
            'created_at' => '2026-09-30 00:00:00', 'updated_at' => $updated, 'deleted_at' => $deleted,
        ]);
    }

    public function test_migration_keeps_latest_valid_active_snapshot_and_archives_every_other_row(): void
    {
        $old = $this->snapshot(100, '2026-10-01 00:00:00');
        $latest = $this->snapshot(80, '2026-10-01 00:10:00');
        $invalid = $this->snapshot(-1, '2026-10-01 00:20:00');
        $deleted = $this->snapshot(120, '2026-10-01 00:30:00', '2026-10-01 00:30:00');
        $uv = $this->snapshot(2, '2026-10-01 00:00:00', type: 'uv');

        $this->migration->up();

        $this->assertSame(2, DB::table('visitor_statistics')->count());
        $this->assertDatabaseHas('visitor_statistics', ['id' => $latest, 'count' => 80]);
        $this->assertDatabaseHas('visitor_statistics', ['id' => $uv, 'count' => 2]);
        $this->assertSame(3, DB::table('visitor_statistics_duplicates')->count());
        foreach ([$old, $invalid, $deleted] as $id) {
            $this->assertDatabaseHas('visitor_statistics_duplicates', ['id' => $id]);
        }
    }

    public function test_timestamp_ties_keep_highest_id_and_deleted_only_groups_remain_deleted(): void
    {
        $this->snapshot(10, '2026-10-01 00:00:00', '2026-10-01 00:00:00');
        $winner = $this->snapshot(20, '2026-10-01 00:00:00', '2026-10-01 00:00:00');
        $this->migration->up();
        $this->assertDatabaseHas('visitor_statistics', ['id' => $winner, 'count' => 20, 'deleted_at' => '2026-10-01 00:00:00']);
        $this->assertSame(1, DB::table('visitor_statistics')->count());
    }

    public function test_groups_with_no_valid_snapshot_abort_without_losing_records(): void
    {
        $this->snapshot(10, '2026-10-01 00:00:00');
        $this->snapshot(20, '2026-10-01 00:10:00');
        $this->snapshot(-1, '2026-10-01 00:00:00', type: 'uv');
        $this->snapshot(-2, '2026-10-01 00:10:00', type: 'uv');
        try {
            $this->migration->up();
            $this->fail('Invalid snapshots must be repaired before migration.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('No valid visitor statistics snapshot', $e->getMessage());
        }
        $this->assertSame(4, DB::table('visitor_statistics')->count());
        $this->assertSame(0, DB::table('visitor_statistics_duplicates')->count());
    }

    public function test_unique_constraint_rejects_duplicate_daily_snapshots(): void
    {
        $this->snapshot(10, '2026-10-01 00:00:00');
        $this->migration->up();
        $this->expectException(QueryException::class);
        $this->snapshot(20, '2026-10-01 00:10:00');
    }

    public function test_rollback_preserves_archive_and_can_be_migrated_again(): void
    {
        $old = $this->snapshot(10, '2026-10-01 00:00:00');
        $this->snapshot(20, '2026-10-01 00:10:00');
        $this->migration->up();
        $this->migration->down();
        $this->assertDatabaseHas('visitor_statistics_duplicates', ['id' => $old, 'count' => 10]);
        $this->snapshot(30, '2026-10-01 00:20:00');
        $this->migration->up();
        $this->assertSame(1, DB::table('visitor_statistics')->count());
        $this->assertSame(2, DB::table('visitor_statistics_duplicates')->count());
        $this->assertDatabaseHas('visitor_statistics', ['count' => 30]);
    }

    public function test_deduplication_and_archiving_continue_across_batch_boundaries(): void
    {
        $rows = [];
        for ($day = 0; $day < 201; $day++) {
            foreach ([10, 20] as $count) {
                $rows[] = [
                    'type' => 'pv', 'date' => now()->subDays($day)->toDateString(), 'count' => $count,
                    'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
                ];
            }
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('visitor_statistics')->insert($chunk);
        }
        $this->migration->up();
        $this->assertSame(201, DB::table('visitor_statistics')->count());
        $this->assertSame(201, DB::table('visitor_statistics_duplicates')->count());
        $this->assertSame(0, DB::table('visitor_statistics')->where('count', '<>', 20)->count());
    }
}
