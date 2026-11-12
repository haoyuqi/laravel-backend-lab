<?php

namespace Tests\Feature\Console;

use App\Models\Visitor;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PruneVisitorDataCommandTest extends TestCase
{
    public function test_default_is_dry_run_and_force_deletes_expired_rows_in_batches(): void
    {
        $expiredVisitor = Visitor::create(['ip' => '198.51.100.10', 'city' => 'Old']);
        $expiredOrphan = Visitor::create(['ip' => '198.51.100.11', 'city' => 'Orphan']);
        $retainedVisitor = Visitor::create(['ip' => '198.51.100.12', 'city' => 'Active']);
        $recentVisitor = Visitor::create(['ip' => '198.51.100.13', 'city' => 'Recent']);

        $this->ageVisitor($expiredVisitor, 40);
        $this->ageVisitor($expiredOrphan, 40);
        $this->ageVisitor($retainedVisitor, 40);
        $this->ageVisitor($recentVisitor, 0);

        $expiredLogs = $this->createLogs($expiredVisitor, 1001, 40);
        $this->createLogs($retainedVisitor, 1, 0);
        $this->createLogs($recentVisitor, 1, 40);

        $this->artisan('visitor-data:prune')
            ->expectsOutput('Dry run: 1002 visitor logs and 2 visitors eligible. Use --force to delete.')
            ->assertSuccessful();
        $this->assertDatabaseCount('visitor_logs', 1003);
        $this->assertDatabaseCount('visitors', 4);

        $this->artisan('visitor-data:prune', ['--force' => true])
            ->expectsOutput('Deleted 1002 visitor logs and 2 visitors.')
            ->assertSuccessful();

        $this->assertDatabaseCount('visitor_logs', 1);
        $this->assertDatabaseMissing('visitors', ['id' => $expiredVisitor->id]);
        $this->assertDatabaseMissing('visitors', ['id' => $expiredOrphan->id]);
        $this->assertDatabaseHas('visitors', ['id' => $retainedVisitor->id]);
        $this->assertDatabaseHas('visitors', ['id' => $recentVisitor->id]);
        $this->assertDatabaseMissing('visitor_logs', ['id' => $expiredLogs[0]]);
    }

    #[DataProvider('invalidDays')]
    public function test_invalid_retention_is_rejected_even_with_force(string $days): void
    {
        $this->artisan('visitor-data:prune', ['--days' => $days, '--force' => true])
            ->expectsOutput('--days must be an integer between 1 and 3650.')
            ->assertFailed();
    }

    public static function invalidDays(): array
    {
        return [['0'], ['-1'], ['1.5'], ['abc'], ['3651']];
    }

    public function test_retention_defaults_to_configuration(): void
    {
        config(['visitor.retention_days' => 10]);

        $expired = Visitor::create(['ip' => '198.51.100.20', 'city' => 'Old']);
        $this->ageVisitor($expired, 20);
        $retained = Visitor::create(['ip' => '198.51.100.21', 'city' => 'Recent']);
        $this->ageVisitor($retained, 5);

        $this->artisan('visitor-data:prune', ['--force' => true])
            ->expectsOutput('Deleted 0 visitor logs and 1 visitors.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('visitors', ['id' => $expired->id]);
        $this->assertDatabaseHas('visitors', ['id' => $retained->id]);
    }

    public function test_minimum_retention_is_accepted(): void
    {
        $this->artisan('visitor-data:prune', ['--days' => '1'])->assertSuccessful();
    }

    private function ageVisitor(Visitor $visitor, int $days): void
    {
        DB::table('visitors')->where('id', $visitor->id)->update([
            'created_at' => now()->subDays($days),
            'updated_at' => now()->subDays($days),
        ]);
    }

    /**
     * @return list<int>
     */
    private function createLogs(Visitor $visitor, int $count, int $ageInDays): array
    {
        $timestamp = now()->subDays($ageInDays);
        $rows = [];

        for ($index = 0; $index < $count; $index++) {
            $rows[] = [
                'visitor_id' => $visitor->id,
                'url' => '/page/'.$index,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        DB::table('visitor_logs')->insert($rows);

        return DB::table('visitor_logs')->where('visitor_id', $visitor->id)->orderBy('id')->pluck('id')->all();
    }
}
