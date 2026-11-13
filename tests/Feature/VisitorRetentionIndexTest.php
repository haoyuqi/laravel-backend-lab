<?php

namespace Tests\Feature;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VisitorRetentionIndexTest extends TestCase
{
    private const INDEXES = [
        'visitors' => 'visitors_retention_created_at_index',
        'visitor_logs' => 'visitor_logs_retention_created_at_index',
    ];

    public function test_visitor_retention_indexes_exist(): void
    {
        foreach (self::INDEXES as $table => $indexName) {
            $this->assertTrue(Schema::hasIndex($table, $indexName));
            $this->assertTrue(Schema::hasIndex($table, ['created_at']));
        }
    }

    public function test_visitor_retention_index_rollback_drops_owned_indexes_by_name(): void
    {
        $connection = DB::connection('testing');
        $connection->getSchemaBuilder();
        $blueprints = [];

        foreach (self::INDEXES as $table => $indexName) {
            $blueprint = new Blueprint($connection, $table);

            Schema::shouldReceive('hasIndex')
                ->once()
                ->with($table, $indexName)
                ->andReturn(true);

            Schema::shouldReceive('table')
                ->once()
                ->with($table, \Mockery::type(Closure::class))
                ->andReturnUsing(function (string $tableName, Closure $callback) use ($blueprint): void {
                    $callback($blueprint);
                });

            $blueprints[$table] = $blueprint;
        }

        $migration = require database_path('migrations/2026_11_13_000000_add_visitor_retention_indexes.php');
        $migration->down();

        foreach (self::INDEXES as $table => $indexName) {
            $command = $blueprints[$table]->getCommands()[0] ?? null;
            $this->assertSame('dropIndex', $command?->name);
            $this->assertSame($indexName, $command?->index);
            $this->assertSame([], $command?->columns);
        }
    }

    public function test_visitor_retention_index_migration_does_not_create_or_drop_unrelated_existing_index(): void
    {
        foreach (self::INDEXES as $table => $indexName) {
            Schema::shouldReceive('hasIndex')
                ->once()
                ->with($table, $indexName)
                ->andReturn(false);

            Schema::shouldReceive('hasIndex')
                ->once()
                ->with($table, ['created_at'])
                ->andReturn(true);

            Schema::shouldReceive('hasIndex')
                ->once()
                ->with($table, $indexName)
                ->andReturn(false);
        }

        Schema::shouldReceive('table')->never();

        $migration = require database_path('migrations/2026_11_13_000000_add_visitor_retention_indexes.php');
        $migration->up();
        $migration->down();

        $this->addToAssertionCount(\Mockery::getContainer()->mockery_getExpectationCount());
    }

    protected function tearDown(): void
    {
        try {
            \Mockery::close();
        } finally {
            Schema::clearResolvedInstance('db.schema');
            parent::tearDown();
        }
    }
}
