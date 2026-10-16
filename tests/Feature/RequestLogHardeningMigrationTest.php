<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class RequestLogHardeningMigrationTest extends TestCase
{
    private object $migration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migration = require database_path('migrations/2026_10_15_000000_harden_request_logs.php');
    }

    public function test_up_repairs_a_partially_created_unique_index_and_down_up_preserve_rows(): void
    {
        $visitorId = DB::table('visitors')->insertGetId([
            'ip' => '203.0.113.80', 'city' => 'Test', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $blackListId = DB::table('black_lists')->insertGetId([
            'ip' => '203.0.113.81', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('visitor_logs')->insert([
            'visitor_id' => $visitorId, 'url' => '/visitor', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('black_list_logs')->insert([
            'black_list_id' => $blackListId, 'url' => '/blocked', 'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::table('visitor_logs', function ($table) {
            $table->dropUnique(['event_id']);
        });
        $this->migration->up();

        $this->assertTrue(Schema::hasIndex('visitor_logs', ['event_id'], 'unique'));
        $this->migration->down();
        $this->assertFalse(Schema::hasColumn('visitor_logs', 'event_id'));
        $this->assertDatabaseHas('visitor_logs', ['visitor_id' => $visitorId, 'url' => '/visitor']);
        $this->assertDatabaseHas('black_list_logs', ['black_list_id' => $blackListId, 'url' => '/blocked']);

        $this->migration->up();
        $this->migration->up();

        $this->assertTrue(Schema::hasColumn('visitor_logs', 'event_id'));
        $this->assertTrue(Schema::hasIndex('black_list_logs', ['event_id'], 'unique'));
        $this->assertDatabaseHas('visitor_logs', ['visitor_id' => $visitorId, 'url' => '/visitor']);
        $this->assertDatabaseHas('black_list_logs', ['black_list_id' => $blackListId, 'url' => '/blocked']);
    }

    public function test_down_refuses_to_shrink_long_urls_without_changing_the_schema(): void
    {
        $visitorId = DB::table('visitors')->insertGetId([
            'ip' => '203.0.113.82', 'city' => 'Test', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('visitor_logs')->insert([
            'visitor_id' => $visitorId,
            'url' => '/'.str_repeat('x', 300),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $this->migration->down();
            $this->fail('Rollback must refuse to shrink URL values longer than 255 characters.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('exceed 255 characters', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('visitor_logs', 'event_id'));
        $this->assertTrue(Schema::hasColumn('black_list_logs', 'event_id'));
        $this->assertSame(301, strlen(DB::table('visitor_logs')->where('visitor_id', $visitorId)->value('url')));
    }
}
