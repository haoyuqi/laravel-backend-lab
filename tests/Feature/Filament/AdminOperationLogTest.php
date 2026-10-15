<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\AdminOperationLogResource;
use App\Filament\Resources\AdminOperationLogResource\Pages\ListAdminOperationLogs;
use App\Filament\Resources\BlackListResource\Pages\CreateBlackList;
use App\Filament\Resources\BlackListResource\Pages\EditBlackList;
use App\Filament\Resources\BlackListResource\Pages\ListBlackLists;
use App\Filament\Resources\VisitorResource\Pages\ListVisitors;
use App\Models\AdminOperationLog;
use App\Models\BlackList;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class AdminOperationLogTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
    }

    public function test_create_edit_table_delete_and_header_delete_are_audited(): void
    {
        Livewire::test(CreateBlackList::class)->fillForm(['ip' => '192.0.2.30'])->call('create')->assertHasNoFormErrors();
        $record = BlackList::where('ip', '192.0.2.30')->firstOrFail();
        Livewire::test(EditBlackList::class, ['record' => $record->id])->fillForm(['ip' => '192.0.2.31'])->call('save')->assertHasNoFormErrors();
        Livewire::test(ListBlackLists::class)->callTableAction('delete', $record);
        $other = BlackList::create(['ip' => '192.0.2.32']);
        Livewire::test(EditBlackList::class, ['record' => $other->id])->callAction('delete');
        $this->assertSame(['create', 'update', 'delete', 'delete'], AdminOperationLog::orderBy('id')->pluck('action')->all());
        $this->assertSame('192.0.2.31', AdminOperationLog::where('action', 'delete')->first()->before['ip']);
    }

    public function test_invalid_form_does_not_create_a_log(): void
    {
        Livewire::test(CreateBlackList::class)->fillForm(['ip' => 'bad'])->call('create')->assertHasFormErrors(['ip' => 'ip']);
        $this->assertDatabaseCount('admin_operation_logs', 0);
        $this->assertDatabaseCount('black_lists', 0);
    }

    public function test_save_without_redirect_updates_the_livewire_record(): void
    {
        $record = BlackList::create(['ip' => '192.0.2.39']);
        $page = Livewire::test(EditBlackList::class, ['record' => $record->id])
            ->fillForm(['ip' => '192.0.2.40'])->call('save', false)->assertHasNoFormErrors();
        $this->assertSame('192.0.2.40', $page->instance()->getRecord()->ip);
        $this->assertSame('192.0.2.40', AdminOperationLog::first()->after['ip']);
    }

    public function test_denied_admin_cannot_invoke_livewire_writes_or_delete_records(): void
    {
        $record = BlackList::create(['ip' => '192.0.2.41']);
        config(['app.admin_emails' => ['another@example.com']]);
        // Direct Livewire tests bypass panel HTTP middleware; service auth still denies writes.
        Livewire::test(CreateBlackList::class)->fillForm(['ip' => '192.0.2.42'])->call('create')->assertForbidden();
        Livewire::test(EditBlackList::class, ['record' => $record->id])
            ->fillForm(['ip' => '192.0.2.42'])->call('save')->assertForbidden();
        Livewire::test(ListBlackLists::class)->callTableAction('delete', $record)->assertForbidden();
        $this->assertDatabaseMissing('black_lists', ['ip' => '192.0.2.42']);
        $this->assertFalse($record->fresh()->trashed());
        $this->assertDatabaseCount('admin_operation_logs', 0);
    }

    public function test_bulk_delete_continues_after_log_failure_without_unaudited_deletes(): void
    {
        $failed = BlackList::create(['ip' => '192.0.2.33']);
        $successful = BlackList::create(['ip' => '192.0.2.34']);
        Event::listen('eloquent.creating: '.AdminOperationLog::class, function (AdminOperationLog $log) use ($failed): void {
            if ($log->subject_id === (string) $failed->id) {
                throw new RuntimeException('Simulated partial failure');
            }
        });
        Livewire::test(ListBlackLists::class)->callTableBulkAction('delete', [$failed, $successful]);
        $this->assertFalse($failed->fresh()->trashed());
        $this->assertTrue($successful->fresh()->trashed());
        $this->assertDatabaseCount('admin_operation_logs', 1);
        $this->assertEquals($successful->id, AdminOperationLog::first()->subject_id);
    }

    public function test_visitor_bulk_add_logs_create_and_restore_but_not_already_active_ip(): void
    {
        $active = BlackList::create(['ip' => '192.0.2.35']);
        $deleted = BlackList::create(['ip' => '192.0.2.36']);
        $deleted->delete();
        $visitors = collect([$active->ip, $deleted->ip, '192.0.2.37'])->map(fn ($ip) => Visitor::create(['ip' => $ip]));
        Livewire::test(ListVisitors::class)->callTableBulkAction('add_to_black_list', $visitors->all());
        $this->assertSame(['create', 'restore'], AdminOperationLog::orderBy('action')->pluck('action')->all());
        $this->assertFalse($deleted->fresh()->trashed());
        $this->assertDatabaseHas('black_lists', ['ip' => '192.0.2.37', 'deleted_at' => null]);
    }

    public function test_only_authorized_admin_can_read_logs(): void
    {
        $url = AdminOperationLogResource::getUrl('index');
        $this->get($url)->assertSuccessful();
        config(['app.admin_emails' => ['another@example.com']]);
        $this->get($url)->assertForbidden();
        Livewire::test(ListAdminOperationLogs::class)->assertForbidden();
        auth()->logout();
        $this->get($url)->assertRedirect('/admin/login');
    }

    public function test_read_only_table_escapes_names_and_supports_search_and_filters(): void
    {
        $first = $this->log(['actor_name' => '<script>alert(1)</script>', 'occurred_at' => now()->subDays(5)]);
        $second = $this->log(['actor_name' => 'Second operator', 'action' => 'delete']);
        Livewire::test(ListAdminOperationLogs::class)
            ->assertCanSeeTableRecords([$second, $first], inOrder: true)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', escape: false)
            ->assertDontSee('<script>alert(1)</script>', escape: false)
            ->assertTableActionDoesNotExist('edit')->assertTableActionDoesNotExist('delete')
            ->assertTableBulkActionDoesNotExist('delete')
            ->searchTable('Second operator')->assertCanSeeTableRecords([$second])->assertCanNotSeeTableRecords([$first])
            ->searchTable('')->filterTable('action', 'create')->assertCanSeeTableRecords([$first])->assertCanNotSeeTableRecords([$second])
            ->filterTable('action', null)->filterTable('occurred_at', ['from' => now()->subDay()->toDateString()])
            ->assertCanSeeTableRecords([$second])->assertCanNotSeeTableRecords([$first]);
        $this->assertFalse(AdminOperationLogResource::canCreate());
        $this->assertFalse(AdminOperationLogResource::canEdit($first));
        $this->assertFalse(AdminOperationLogResource::canDelete($first));
        $this->assertFalse(AdminOperationLogResource::canDeleteAny());
        $this->assertSame(['index'], array_keys(AdminOperationLogResource::getPages()));
    }

    public function test_logs_are_paginated_without_actor_or_subject_relationship_queries(): void
    {
        $logs = collect(range(1, 30))->map(fn () => $this->log());
        $connection = (new AdminOperationLog)->getConnection();
        $connection->enableQueryLog();
        $page = Livewire::test(ListAdminOperationLogs::class)->set('tableRecordsPerPage', 10)->assertCanSeeTableRecords($logs->reverse()->take(10))
            ->assertCanNotSeeTableRecords($logs->take(5));
        $selects = collect($connection->getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'select') && str_contains($query['query'], 'admin_operation_logs'));
        $this->assertLessThanOrEqual(4, $selects->count());
        $connection->disableQueryLog();
        $page->set('paginators.page', 3)->assertCanSeeTableRecords($logs->take(5));
    }

    private function log(array $attributes = []): AdminOperationLog
    {
        $log = new AdminOperationLog;
        $log->forceFill(array_replace([
            'actor_id' => $this->admin->id, 'actor_name' => $this->admin->name, 'action' => 'create',
            'subject_id' => 1, 'before' => null, 'after' => ['ip' => '192.0.2.38', 'deleted' => false], 'occurred_at' => now(),
        ], $attributes))->save();

        return $log;
    }
}
