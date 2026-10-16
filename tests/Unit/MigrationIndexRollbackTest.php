<?php

namespace Tests\Unit;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationIndexRollbackTest extends TestCase
{
    public function test_legacy_index_rollbacks_resolve_index_names_from_columns(): void
    {
        $migrations = [
            ['2020_07_14_214753_black_list_logs_add_index.php', 'BlackListLogsAddIndex', 'black_list_logs', 'black_list_id'],
            ['2020_07_14_215303_visitor_logs_add_index.php', 'VisitorLogsAddIndex', 'visitor_logs', 'visitor_id'],
        ];

        foreach ($migrations as [$file, $class, $table, $column]) {
            $connection = DB::connection('testing');
            $connection->getSchemaBuilder();
            $blueprint = new Blueprint($connection, $table);
            Schema::shouldReceive('table')
                ->once()
                ->with($table, \Mockery::type(Closure::class))
                ->andReturnUsing(function (string $tableName, Closure $callback) use ($blueprint): void {
                    $callback($blueprint);
                });

            include_once database_path('migrations/'.$file);
            $migration = new $class;
            $migration->down();

            $command = $blueprint->getCommands()[0] ?? null;
            $this->assertSame('dropIndex', $command?->name);
            $this->assertSame($table.'_'.$column.'_index', $command?->index);
            $this->assertSame([$column], $command?->columns);
        }
    }
}
