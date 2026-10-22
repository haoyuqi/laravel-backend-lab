<?php

namespace Tests\Feature\Service;

use App\Models\AdminOperationLog;
use App\Models\AuditFixture;
use App\Models\AuditSoftFixture;
use App\Models\User;
use App\Observers\AdminOperationObserver;
use App\Service\AdminOperationRecorder;
use App\Support\AdminOperationContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class AdminOperationRecorderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('audit_fixture_links');
        Schema::dropIfExists('audit_fixtures');

        Schema::create('audit_fixtures', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('status');
            $table->json('settings')->nullable();
            $table->string('password')->nullable();
            $table->softDeletes();
        });
        Schema::create('audit_fixture_links', function (Blueprint $table): void {
            $table->string('parent_id');
            $table->string('related_id');
            $table->integer('weight');
            $table->primary(['parent_id', 'related_id']);
        });

        // In MySQL, DDL statements (Schema::create / dropIfExists) trigger an implicit commit,
        // which commits the transaction started by RefreshDatabase and leaves MySQL in
        // autocommit mode while Laravel's connection transaction counter remains at 1.
        // Re-synchronize the transaction state so nested transactions and savepoints work properly.
        if (DB::connection()->getDriverName() === 'mysql') {
            while (DB::connection()->transactionLevel() > 0) {
                DB::connection()->rollBack();
            }
            DB::connection()->beginTransaction();
        }

        $this->actingAs(User::factory()->create());

        config(['admin-operation-logs.models.'.AuditFixture::class => [
            'type' => 'fixture', 'fields' => ['name', 'status', 'settings', 'password'],
            'relationship_fields' => ['links'],
        ]]);
        config(['admin-operation-logs.models.'.AuditSoftFixture::class => [
            'type' => 'soft_fixture', 'fields' => ['name', 'status'],
        ]]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('audit_fixture_links');
        Schema::dropIfExists('audit_fixtures');

        parent::tearDown();
    }

    public function test_generic_uuid_subject_and_persisted_snapshots_preserve_untouched_database_fields(): void
    {
        $fixture = AuditFixture::create(['id' => '4de96464-5fc7-43f8-a719-a927880d2653', 'name' => 'Before', 'status' => 'old']);
        DB::table('audit_fixtures')->where('id', $fixture->id)->update(['status' => 'new']);
        app(AdminOperationRecorder::class)->run($fixture->getConnection(), 'fixture.rename', fn () => $fixture->update(['name' => 'After']));
        $log = AdminOperationLog::sole();
        $this->assertSame('fixture', $log->subject_type);
        $this->assertSame($fixture->id, $log->subject_id);
        $this->assertSame('fixture.rename', $log->action_key);
        $this->assertSame('new', $log->before['status']);
        $this->assertSame('new', $log->after['status']);
        $this->assertSame('After', $log->after['name']);
        $this->assertNotNull($log->operation_id);
        $this->assertNull(app(AdminOperationContext::class)->actor);
    }

    public function test_generic_create_delete_and_safe_nested_fields(): void
    {
        $fixture = app(AdminOperationRecorder::class)->run(DB::connection(), 'fixture.create', fn () => AuditFixture::create([
            'id' => 'uuid-like-key', 'name' => '<script>name</script>', 'status' => 'active', 'password' => 'sensitive fixture',
            'settings' => ['theme' => 'dark', 'token' => 'sensitive fixture', 'nested' => ['password' => 'sensitive fixture', 'count' => 1]],
        ]));
        $log = AdminOperationLog::sole();
        $this->assertArrayNotHasKey('password', $log->after);
        $this->assertSame(['theme' => 'dark', 'nested' => ['count' => 1]], $log->after['settings']);
        app(AdminOperationRecorder::class)->run(DB::connection(), 'fixture.delete', fn () => $fixture->delete());
        $this->assertNull(AdminOperationLog::where('action', 'delete')->sole()->after);
    }

    public function test_unchanged_safe_state_and_nonadmin_direct_writes_are_not_logged(): void
    {
        $fixture = AuditFixture::create(['id' => 'no-op', 'name' => 'Name', 'status' => 'active']);
        app(AdminOperationRecorder::class)->run(DB::connection(), 'fixture.same', fn () => $fixture->update(['name' => 'Name']));
        $fixture->update(['name' => 'CLI-like direct write']);
        $this->assertDatabaseCount('admin_operation_logs', 0);
    }

    public function test_missing_policy_is_rejected_before_write(): void
    {
        config(['admin-operation-logs.models.'.AuditFixture::class => null]);
        $this->expectException(LogicException::class);
        try {
            app(AdminOperationRecorder::class)->run(DB::connection(), 'fixture.create', fn () => AuditFixture::create(['id' => 'blocked', 'name' => 'Name', 'status' => 'active']));
        } finally {
            $this->assertDatabaseMissing('audit_fixtures', ['id' => 'blocked']);
            $this->assertNull(app(AdminOperationContext::class)->actor);
        }
    }

    public function test_nontransactional_write_in_admin_context_is_rejected(): void
    {
        // RefreshDatabase supplies an outer transaction: require the second connection
        // to prove the guard fails before a SQL statement rather than using that wrapper.
        $context = app(AdminOperationContext::class);
        $this->expectException(LogicException::class);
        $context->within(auth()->user(), 'fixture.wrong_connection', 'another-connection', fn () => AuditFixture::create(['id' => 'blocked', 'name' => 'Name', 'status' => 'active']));
    }

    public function test_log_failure_rolls_back_generic_changes_and_clears_context(): void
    {
        $fixture = AuditFixture::create(['id' => 'rollback', 'name' => 'Before', 'status' => 'active']);
        Event::listen('eloquent.creating: '.AdminOperationLog::class, fn () => throw new RuntimeException('Audit unavailable'));
        try {
            app(AdminOperationRecorder::class)->run(DB::connection(), 'fixture.rename', fn () => $fixture->update(['name' => 'After']));
            $this->fail('Audit failure must rollback.');
        } catch (RuntimeException) {
            $this->assertSame('Before', $fixture->fresh()->name);
            $this->assertDatabaseCount('admin_operation_logs', 0);
            $this->assertSame([], app(AdminOperationContext::class)->pending);
        }
    }

    public function test_explicit_builder_mutation_uses_same_transaction_and_snapshots(): void
    {
        $fixture = AuditFixture::create(['id' => 'builder', 'name' => 'Before', 'status' => 'active']);
        app(AdminOperationRecorder::class)->explicit($fixture, 'update', fn ($current) => DB::table('audit_fixtures')->where('id', $current->id)->update(['name' => 'After']));
        $this->assertSame('Before', AdminOperationLog::sole()->before['name']);
        $this->assertSame('After', AdminOperationLog::sole()->after['name']);
    }

    public function test_bounded_snapshots_fail_before_audited_change_can_commit(): void
    {
        $fixture = AuditFixture::create(['id' => 'too-large', 'name' => 'Name', 'status' => 'active']);
        $this->expectException(LogicException::class);
        try {
            app(AdminOperationRecorder::class)->run(DB::connection(), 'fixture.settings', fn () => $fixture->update(['settings' => ['value' => str_repeat('x', 17000)]]));
        } finally {
            $this->assertNull($fixture->fresh()->settings);
            $this->assertDatabaseCount('admin_operation_logs', 0);
        }
    }

    public function test_explicit_pivot_attach_update_detach_and_noop_use_persisted_safe_snapshots(): void
    {
        $fixture = AuditFixture::create(['id' => 'parent', 'name' => 'Name', 'status' => 'active']);
        AuditFixture::create(['id' => 'related', 'name' => 'Related', 'status' => 'active']);
        $snapshot = fn ($model) => ['links' => $model->links()->orderBy('audit_fixtures.id')->get()->map(fn ($related) => ['id' => $related->id, 'weight' => $related->pivot->weight])->all()];
        $recorder = app(AdminOperationRecorder::class);
        $recorder->explicit($fixture, 'update', fn ($current) => $current->links()->attach('related', ['weight' => 1]), $snapshot);
        $recorder->explicit($fixture, 'update', fn ($current) => $current->links()->updateExistingPivot('related', ['weight' => 2]), $snapshot);
        $recorder->explicit($fixture, 'update', fn ($current) => $current->links()->updateExistingPivot('related', ['weight' => 2]), $snapshot);
        $recorder->explicit($fixture, 'update', fn ($current) => $current->links()->detach('related'), $snapshot);
        $logs = AdminOperationLog::orderBy('id')->get();
        $this->assertCount(3, $logs);
        $this->assertSame([], $logs[0]->before['links']);
        $this->assertSame([['id' => 'related', 'weight' => 1]], $logs[0]->after['links']);
        $this->assertSame([['id' => 'related', 'weight' => 2]], $logs[1]->after['links']);
        $this->assertSame([], $logs[2]->after['links']);
        Event::listen('eloquent.creating: '.AdminOperationLog::class, fn () => throw new RuntimeException('Audit unavailable'));
        try {
            $recorder->explicit($fixture, 'update', fn ($current) => $current->links()->attach('related', ['weight' => 3]), $snapshot);
            $this->fail('Pivot change must rollback with its audit.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('audit_fixture_links', 0);
            $this->assertDatabaseCount('admin_operation_logs', 3);
            $this->assertSame([], app(AdminOperationContext::class)->explicitSubjects);
        }
    }

    public function test_explicit_eloquent_callback_does_not_duplicate_audit(): void
    {
        $fixture = AuditFixture::create(['id' => 'explicit-model', 'name' => 'Before', 'status' => 'active']);
        app(AdminOperationRecorder::class)->explicit($fixture, 'update', fn ($current) => $current->update(['name' => 'After']));
        $this->assertDatabaseCount('admin_operation_logs', 1);
        $this->assertSame('After', AdminOperationLog::sole()->after['name']);
    }

    public function test_nested_audit_context_rejects_another_actor_and_connection_and_cleans_up(): void
    {
        $context = app(AdminOperationContext::class);
        $actor = auth()->user();
        $otherActor = User::factory()->create();
        $connection = DB::connection()->getName();

        $context->within($actor, 'fixture.outer', $connection, function () use ($context, $actor, $otherActor, $connection): void {
            try {
                $context->within($otherActor, 'fixture.inner', $connection, fn () => null);
                $this->fail('A nested operation cannot switch actors.');
            } catch (LogicException $exception) {
                $this->assertStringContainsString('cannot span actors', $exception->getMessage());
            }

            try {
                $context->within($actor, 'fixture.inner', $connection.'-other', fn () => null);
                $this->fail('A nested operation cannot switch connections.');
            } catch (LogicException $exception) {
                $this->assertStringContainsString('cannot span actors', $exception->getMessage());
            }

            $this->assertSame($actor, $context->actor);
            $this->assertSame('fixture.outer', $context->actionKey);
        });

        $this->assertNull($context->actor);
        $this->assertNull($context->actionKey);
        $this->assertNull($context->operationId);
        $this->assertNull($context->connection);
        $this->assertSame([], $context->pending);
        $this->assertSame([], $context->explicitSubjects);
    }

    public function test_explicit_mutation_returning_false_rolls_back_and_clears_context(): void
    {
        $fixture = AuditFixture::create(['id' => 'canceled-explicit', 'name' => 'Before', 'status' => 'active']);

        try {
            app(AdminOperationRecorder::class)->explicit($fixture, 'update', function ($current) {
                DB::table('audit_fixtures')->where('id', $current->id)->update(['name' => 'After']);

                return false;
            });
            $this->fail('A canceled explicit mutation must throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Admin operation was canceled.', $exception->getMessage());
        }

        $this->assertSame('Before', $fixture->fresh()->name);
        $this->assertDatabaseCount('admin_operation_logs', 0);
        $this->assertNull(app(AdminOperationContext::class)->actor);
        $this->assertSame([], app(AdminOperationContext::class)->explicitSubjects);
    }

    public function test_explicit_relationship_snapshot_rejects_unapproved_fields(): void
    {
        $fixture = AuditFixture::create(['id' => 'unsafe-relationship', 'name' => 'Before', 'status' => 'active']);

        $this->expectException(LogicException::class);
        app(AdminOperationRecorder::class)->explicit($fixture, 'update', fn () => null, fn () => ['unapproved' => 'value']);
    }

    public function test_explicit_relationship_snapshot_rejects_sensitive_and_duplicate_fields(): void
    {
        $fixture = AuditFixture::create(['id' => 'sensitive-relationship', 'name' => 'Before', 'status' => 'active']);
        $recorder = app(AdminOperationRecorder::class);

        foreach ([['password' => 'private'], ['name' => 'duplicate']] as $relationshipValues) {
            config(['admin-operation-logs.models.'.AuditFixture::class.'.relationship_fields' => array_keys($relationshipValues)]);

            try {
                $recorder->explicit($fixture, 'update', fn () => null, fn () => $relationshipValues);
                $this->fail('Sensitive and duplicate relationship fields must be rejected.');
            } catch (LogicException $exception) {
                $this->assertStringContainsString('sensitive or duplicate', $exception->getMessage());
            }
        }
    }

    public function test_explicit_relationship_snapshot_rejects_nested_and_object_values(): void
    {
        $fixture = AuditFixture::create(['id' => 'unsafe-values', 'name' => 'Before', 'status' => 'active']);
        $recorder = app(AdminOperationRecorder::class);
        config(['admin-operation-logs.models.'.AuditFixture::class.'.relationship_fields' => ['links']]);

        foreach ([['links' => ['one' => ['two' => ['three' => ['four' => ['five' => 'deep']]]]]], ['links' => new \stdClass]] as $values) {
            try {
                $recorder->explicit($fixture, 'update', fn () => null, fn () => $values);
                $this->fail('Unsafe relationship snapshot values must be rejected.');
            } catch (LogicException $exception) {
                $this->assertStringContainsString('Admin audit snapshots', $exception->getMessage());
            }
        }
    }

    public function test_observer_ignores_completion_events_without_a_matching_pending_write(): void
    {
        $fixture = AuditFixture::create(['id' => 'orphan-completion', 'name' => 'Name', 'status' => 'active']);
        $context = app(AdminOperationContext::class);
        $observer = app(AdminOperationObserver::class);

        $context->within(auth()->user(), 'fixture.orphan', DB::connection()->getName(), function () use ($observer, $fixture): void {
            $observer->handle('eloquent.updated: '.AuditFixture::class, [$fixture]);
        });

        $this->assertDatabaseCount('admin_operation_logs', 0);
    }

    public function test_canceled_restore_does_not_suppress_later_save_in_same_context(): void
    {
        $fixture = AuditSoftFixture::create(['id' => 'canceled-restore', 'name' => 'Before', 'status' => 'active']);
        $fixture->delete();
        $cancel = true;
        Event::listen('eloquent.saving: '.AuditSoftFixture::class, function () use (&$cancel) {
            if ($cancel) {
                $cancel = false;

                return false;
            }
        });
        app(AdminOperationRecorder::class)->run(DB::connection(), 'fixture.retry', function () use ($fixture): void {
            $this->assertFalse($fixture->restore());
            $this->assertTrue($fixture->fresh()->trashed());
            $this->assertSame([], app(AdminOperationContext::class)->pending);
            $fixture->update(['name' => 'After']);
            $fixture->update(['status' => 'changed']);
        });
        $logs = AdminOperationLog::orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertSame('restore', $logs[0]->action);
        $this->assertTrue($logs[0]->before['deleted']);
        $this->assertFalse($logs[0]->after['deleted']);
        $this->assertSame('After', $logs[0]->after['name']);
        $this->assertSame('update', $logs[1]->action);
        $this->assertSame('changed', $logs[1]->after['status']);
        $this->assertSame([], app(AdminOperationContext::class)->pending);
    }

    public function test_stale_active_object_update_does_not_report_a_restore(): void
    {
        $fixture = AuditSoftFixture::create(['id' => 'stale-active', 'name' => 'Before', 'status' => 'active']);
        $fixture->fresh()->delete();
        app(AdminOperationRecorder::class)->run(DB::connection(), 'fixture.stale', fn () => $fixture->update(['name' => 'After']));
        $log = AdminOperationLog::sole();
        $this->assertSame('update', $log->action);
        $this->assertTrue($log->before['deleted']);
        $this->assertTrue($log->after['deleted']);
        $this->assertTrue($fixture->fresh()->trashed());
    }

    public function test_sensitive_only_writes_are_logged_without_exposing_their_values(): void
    {
        $fixture = AuditFixture::create(['id' => 'sensitive-change', 'name' => 'Name', 'status' => 'active', 'password' => 'before-secret']);
        $recorder = app(AdminOperationRecorder::class);
        $recorder->run(DB::connection(), 'fixture.password', fn () => $fixture->update(['password' => 'after-secret']));
        $recorder->explicit($fixture, 'update', fn ($current) => DB::table('audit_fixtures')->where('id', $current->id)->update(['password' => 'another-secret']));
        $logs = AdminOperationLog::all();
        $this->assertCount(2, $logs);
        foreach ($logs as $log) {
            $this->assertSame('update', $log->action);
            $this->assertSame($log->before, $log->after);
            $this->assertArrayNotHasKey('password', $log->before);
            $this->assertArrayNotHasKey('password', $log->after);
        }
    }
}

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuditFixture extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function links(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'audit_fixture_links', 'parent_id', 'related_id')->withPivot('weight');
    }
}

class AuditSoftFixture extends AuditFixture
{
    use SoftDeletes;

    protected $table = 'audit_fixtures';
}
