<?php

namespace Tests\Feature\Console;

use App\Models\AdminOperationLog;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PruneAdminOperationLogsCommandTest extends TestCase
{
    public function test_default_is_dry_run_and_force_deletes_only_expired_logs_in_batches(): void
    {
        $this->travelTo(now()->startOfSecond());
        $expired = ['actor_id' => 1, 'actor_name' => 'Operator', 'action' => 'create', 'subject_id' => 1,
            'before' => null, 'after' => null, 'occurred_at' => now()->subDays(366)];
        DB::table('admin_operation_logs')->insert(array_fill(0, 1001, $expired));
        DB::table('admin_operation_logs')->insert(array_replace($expired, ['occurred_at' => now()->subDays(365)]));
        DB::table('admin_operation_logs')->insert(array_replace($expired, ['occurred_at' => now()]));

        $this->artisan('admin-operation-logs:prune')->expectsOutput('Dry run: 1001 logs eligible. Use --force to delete.')->assertSuccessful();
        $this->assertDatabaseCount('admin_operation_logs', 1003);
        $this->artisan('admin-operation-logs:prune', ['--force' => true])->expectsOutput('Deleted 1001 admin operation logs.')->assertSuccessful();
        $this->assertDatabaseCount('admin_operation_logs', 2);
        $this->assertTrue(AdminOperationLog::oldest('occurred_at')->first()->occurred_at->equalTo(now()->subDays(365)));
    }

    #[DataProvider('invalidDays')]
    public function test_invalid_retention_is_rejected_even_with_force(string $days): void
    {
        $this->artisan('admin-operation-logs:prune', ['--days' => $days, '--force' => true])->assertFailed();
    }

    public static function invalidDays(): array
    {
        return [['0'], ['29'], ['-1'], ['1.5'], ['abc'], ['36501']];
    }

    public function test_minimum_retention_is_accepted(): void
    {
        $this->artisan('admin-operation-logs:prune', ['--days' => '30'])->assertSuccessful();
    }
}
