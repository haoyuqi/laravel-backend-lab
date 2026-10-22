<?php

namespace Tests\Feature\Service;

use App\Models\AdminOperationLog;
use App\Models\BlackList;
use App\Models\User;
use App\Service\AdminBlackListOperations;
use App\Support\IpAddress;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AdminBlackListOperationsTest extends TestCase
{
    private AdminBlackListOperations $operations;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operations = app(AdminBlackListOperations::class);
        $this->actor = User::factory()->create(['name' => 'Original name']);
        $this->actingAs($this->actor);
    }

    public function test_records_only_actual_changes_with_allowlisted_snapshots(): void
    {
        $record = $this->operations->create(['ip' => '192.0.2.1', 'password' => 'not stored']);
        $this->operations->update($record, ['ip' => '192.0.2.1']);
        $this->operations->addIp('192.0.2.1');
        $this->operations->update($record, ['ip' => '192.0.2.2']);
        $this->operations->delete($record);
        $this->operations->delete($record);
        $this->operations->addIp('192.0.2.2');

        $logs = AdminOperationLog::orderBy('id')->get();
        $this->assertSame(['create', 'update', 'delete', 'restore'], $logs->pluck('action')->all());
        $this->assertNull($logs[0]->before);
        $this->assertSame(['ip' => '192.0.2.1', 'deleted' => false], $logs[0]->after);
        $this->assertSame($logs[0]->after, $logs[1]->before);
        $this->assertSame(['ip' => '192.0.2.2', 'deleted' => true], $logs[2]->after);
        $this->assertSame($logs[2]->after, $logs[3]->before);
        $this->assertSame(['ip' => '192.0.2.2', 'deleted' => false], $logs[3]->after);
        foreach ($logs as $log) {
            $this->assertEquals($record->id, $log->subject_id);
            $this->assertEquals($this->actor->id, $log->actor_id);
            $this->assertSame('Original name', $log->actor_name);
            $this->assertNotNull($log->occurred_at);
        }
    }

    public function test_update_snapshots_the_locked_database_state_not_stale_model(): void
    {
        $record = BlackList::create(['ip' => '192.0.2.3']);
        BlackList::whereKey($record->id)->update(['ip' => '192.0.2.4']);
        $this->operations->update($record, ['ip' => '192.0.2.5']);
        $this->assertSame('192.0.2.4', AdminOperationLog::first()->before['ip']);
    }

    public function test_history_survives_actor_and_subject_removal_and_direct_writes_are_not_audited(): void
    {
        $record = $this->operations->create(['ip' => '192.0.2.6']);
        $this->actor->update(['name' => 'Renamed']);
        $this->actor->delete();
        $record->forceDelete();
        BlackList::create(['ip' => '192.0.2.7']);
        $this->assertDatabaseCount('admin_operation_logs', 1);
        $this->assertSame('Original name', AdminOperationLog::first()->actor_name);
    }

    #[DataProvider('mutationCases')]
    public function test_log_insert_failure_rolls_back_each_mutation(string $operation, string $cancelEvent): void
    {
        $record = $this->fixture($operation);
        Event::listen('eloquent.creating: '.AdminOperationLog::class, fn () => throw new RuntimeException('Simulated audit failure'));
        try {
            $this->mutate($operation, $record);
            $this->fail('Audit failure must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated audit failure', $exception->getMessage());
        }
        $this->assertUnchanged($operation, $record);
    }

    #[DataProvider('mutationCases')]
    public function test_canceled_model_events_leave_no_change_or_log(string $operation, string $cancelEvent): void
    {
        $record = $this->fixture($operation);
        Event::listen('eloquent.'.$cancelEvent.': '.BlackList::class, fn () => false);
        try {
            $this->mutate($operation, $record);
            $this->fail('Canceled mutation must fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('canceled', $exception->getMessage());
        }
        $this->assertUnchanged($operation, $record);
    }

    public function test_canceled_log_insert_rolls_back_business_write(): void
    {
        Event::listen('eloquent.creating: '.AdminOperationLog::class, fn () => false);
        $this->expectException(RuntimeException::class);
        try {
            $this->operations->create(['ip' => '192.0.2.9']);
        } finally {
            $this->assertDatabaseMissing('black_lists', ['ip' => '192.0.2.9']);
            $this->assertDatabaseCount('admin_operation_logs', 0);
        }
    }

    public function test_duplicate_ip_fails_without_false_audit(): void
    {
        BlackList::create(['ip' => '192.0.2.10']);
        $this->expectException(ValidationException::class);
        try {
            $this->operations->create(['ip' => '192.0.2.10']);
        } finally {
            $this->assertDatabaseCount('black_lists', 1);
            $this->assertDatabaseCount('admin_operation_logs', 0);
        }
    }

    public function test_canonical_ipv6_duplicate_of_legacy_row_is_rejected_on_create_and_update(): void
    {
        $legacy = BlackList::withoutEvents(fn () => BlackList::query()->create(['ip' => '2001:0db8:0000:0000:0000:0000:0000:0001']));
        $other = BlackList::create(['ip' => '192.0.2.13']);

        try {
            $this->operations->create(['ip' => '2001:db8::1']);
            $this->fail('Equivalent IPv6 address must be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('black_lists', 2);
            $this->assertDatabaseCount('admin_operation_logs', 0);
        }

        try {
            $this->operations->update($other, ['ip' => '2001:db8::1']);
            $this->fail('Equivalent IPv6 address must be rejected during update.');
        } catch (ValidationException) {
            $this->assertSame('192.0.2.13', $other->fresh()->ip);
            $this->assertSame($legacy->id, BlackList::findOrFail($legacy->id)->id);
            $this->assertDatabaseCount('admin_operation_logs', 0);
        }
    }

    public function test_ipv6_lookup_scans_legacy_text_forms_not_in_the_common_form_list(): void
    {
        $legacy = BlackList::withoutEvents(function () {
            $record = new BlackList(['ip' => '2001:0db8:0:0:0:0:0:1']);
            $record->forceFill(['deleted_at' => now()->subDay()]);
            $record->save();

            return $record;
        });

        $restored = $this->operations->addIp('2001:db8::1');

        $this->assertSame($legacy->id, $restored->id);
        $this->assertFalse($restored->fresh()->trashed());
        $this->assertSame(['restore'], AdminOperationLog::pluck('action')->all());
    }

    public function test_ipv6_lookup_returns_null_when_no_equivalent_record_exists(): void
    {
        $this->assertNull(IpAddress::findBlacklistRecord(BlackList::query(), '2001:db8::2'));
    }

    public function test_update_of_a_soft_deleted_record_is_not_found(): void
    {
        $record = BlackList::create(['ip' => '203.0.113.70']);
        $record->delete();

        $this->expectException(ModelNotFoundException::class);
        $this->operations->update($record, ['ip' => '203.0.113.71']);
    }

    public function test_non_blacklist_unique_database_errors_are_rethrown(): void
    {
        $exception = new QueryException('testing', 'insert into black_lists', [], new \PDOException('another unique constraint'));
        $exception->errorInfo = ['23000', 19, 'users.email_unique'];
        Event::listen('eloquent.saving: '.BlackList::class, fn () => throw $exception);

        try {
            $this->operations->create(['ip' => '203.0.113.72']);
            $this->fail('An unrelated unique constraint must remain a database error.');
        } catch (QueryException $caught) {
            $this->assertSame($exception, $caught);
        }

        $this->assertDatabaseCount('black_lists', 0);
        $this->assertDatabaseCount('admin_operation_logs', 0);
    }

    public function test_unique_index_race_is_reported_as_ip_validation_error(): void
    {
        $ip = '192.0.2.14';
        Event::listen('eloquent.creating: '.BlackList::class, function () use ($ip): void {
            DB::table('black_lists')->insert([
                'ip' => $ip,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        try {
            $this->operations->create(['ip' => $ip]);
            $this->fail('A concurrent unique-key conflict must be reported as a validation error.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('black_lists', 0);
            $this->assertDatabaseCount('admin_operation_logs', 0);
        }
    }

    public function test_invalid_ip_is_rejected_without_write(): void
    {
        $this->expectException(ValidationException::class);
        try {
            $this->operations->create(['ip' => 'invalid']);
        } finally {
            $this->assertDatabaseCount('black_lists', 0);
            $this->assertDatabaseCount('admin_operation_logs', 0);
        }
    }

    public function test_guest_and_denied_admin_cannot_write(): void
    {
        foreach ([null, $this->actor] as $actor) {
            auth()->logout();
            config(['app.admin_emails' => ['another@example.com']]);
            if ($actor) {
                $this->actingAs($actor);
            }
            try {
                $this->operations->create(['ip' => '192.0.2.11']);
                $this->fail('Unauthorized actor must be rejected.');
            } catch (AuthorizationException) {
                $this->assertDatabaseCount('black_lists', 0);
                $this->assertDatabaseCount('admin_operation_logs', 0);
            }
        }
    }

    public function test_logs_reject_eloquent_updates_and_deletes(): void
    {
        $this->operations->create(['ip' => '192.0.2.12']);
        $log = AdminOperationLog::first();
        foreach (['update', 'delete'] as $operation) {
            try {
                if ($operation === 'update') {
                    $log->actor_name = 'Changed';
                    $log->save();
                } else {
                    $log->delete();
                }
                $this->fail('Log must reject mutation.');
            } catch (LogicException) {
                $this->assertSame('Original name', $log->fresh()->actor_name);
            }
        }
        $this->assertDatabaseCount('admin_operation_logs', 1);
    }

    public static function mutationCases(): array
    {
        return [['create', 'creating'], ['update', 'saving'], ['delete', 'deleting'], ['restore', 'restoring'], ['restore', 'saving']];
    }

    private function fixture(string $operation): ?BlackList
    {
        if ($operation === 'create') {
            return null;
        }
        $record = BlackList::create(['ip' => '192.0.2.8']);
        if ($operation === 'restore') {
            $record->delete();
        }

        return $record;
    }

    private function mutate(string $operation, ?BlackList $record): void
    {
        match ($operation) {
            'create' => $this->operations->create(['ip' => '192.0.2.8']),
            'update' => $this->operations->update($record, ['ip' => '192.0.2.9']),
            'delete' => $this->operations->delete($record),
            'restore' => $this->operations->addIp('192.0.2.8'),
        };
    }

    private function assertUnchanged(string $operation, ?BlackList $record): void
    {
        $this->assertDatabaseCount('admin_operation_logs', 0);
        if ($operation === 'create') {
            $this->assertDatabaseCount('black_lists', 0);
        } else {
            $this->assertSame('192.0.2.8', $record->fresh()->ip);
            $this->assertSame($operation === 'restore', $record->fresh()->trashed());
        }
    }
}
