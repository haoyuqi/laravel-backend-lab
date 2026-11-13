<?php

namespace Tests\Feature;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VisitorRetentionIndexTest extends TestCase
{
    public function test_visitor_retention_indexes_exist(): void
    {
        $this->assertTrue(Schema::hasIndex('visitors', ['created_at']));
        $this->assertTrue(Schema::hasIndex('visitor_logs', ['created_at']));
    }

    public function test_visitor_retention_index_rollback_drops_created_at_indexes(): void
    {
        $connection = DB::connection('testing');
        $connection->getSchemaBuilder();
        $blueprints = [];

        foreach (['visitors', 'visitor_logs'] as $table) {
            $blueprint = new Blueprint($connection, $table);

            Schema::shouldReceive('hasIndex')
                ->once()
                ->with($table, ['created_at'])
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

        foreach (['visitors', 'visitor_logs'] as $table) {
            $command = $blueprints[$table]->getCommands()[0] ?? null;
            $this->assertSame('dropIndex', $command?->name);
            $this->assertSame($table.'_created_at_index', $command?->index);
            $this->assertSame(['created_at'], $command?->columns);
        }
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
