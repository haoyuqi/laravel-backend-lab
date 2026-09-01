<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\BlackListLogResource;
use App\Filament\Resources\BlackListResource;
use App\Filament\Resources\BlackListResource\Pages\CreateBlackList;
use App\Filament\Resources\BlackListResource\Pages\EditBlackList;
use App\Filament\Resources\BlackListResource\Pages\ListBlackLists;
use App\Models\BlackList;
use App\Models\BlackListLog;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class BlackListResourceTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    public function test_unauthenticated_user_cannot_access_black_lists_index(): void
    {
        $response = $this->get(BlackListResource::getUrl('index'));

        $response->assertRedirect('/filament/login');
    }

    public function test_authenticated_admin_can_access_black_lists_index(): void
    {
        $response = $this->actingAs($this->admin)->get(BlackListResource::getUrl('index'));

        $response->assertSuccessful();
    }

    public function test_black_lists_table_can_render_records_with_all_columns(): void
    {
        $blackList = BlackList::create(['ip' => '192.168.20.1']);
        Visitor::create(['ip' => '192.168.20.1', 'city' => 'Hangzhou']);

        Livewire::actingAs($this->admin)
            ->test(ListBlackLists::class)
            ->searchTable('192.168.20.1')
            ->assertCanSeeTableRecords([$blackList])
            ->assertCanRenderTableColumn('ip')
            ->assertCanRenderTableColumn('city.city')
            ->assertCanRenderTableColumn('today_logs_count')
            ->assertCanRenderTableColumn('all_logs_count')
            ->assertCanRenderTableColumn('created_at')
            ->assertCanRenderTableColumn('updated_at');
    }

    public function test_today_and_all_logs_counts_are_calculated_correctly(): void
    {
        $blackList = BlackList::create(['ip' => '192.168.20.2']);

        $todayLog = new BlackListLog;
        $todayLog->url = 'https://example.com/blocked-today';
        $todayLog->created_at = now();
        $blackList->logs()->save($todayLog);

        $pastLog = new BlackListLog;
        $pastLog->url = 'https://example.com/blocked-past';
        $blackList->logs()->save($pastLog);
        $pastLog->created_at = now()->subDays(3);
        $pastLog->save();

        $reloaded = BlackListResource::getEloquentQuery()->find($blackList->id);
        $this->assertEquals(1, $reloaded->today_logs_count);
        $this->assertEquals(2, $reloaded->all_logs_count);
    }

    public function test_can_search_black_lists_by_ip(): void
    {
        $b1 = BlackList::create(['ip' => '10.10.10.10']);
        $b2 = BlackList::create(['ip' => '172.20.20.20']);

        Livewire::actingAs($this->admin)
            ->test(ListBlackLists::class)
            ->searchTable('10.10.10.10')
            ->assertCanSeeTableRecords([$b1])
            ->assertCanNotSeeTableRecords([$b2]);
    }

    public function test_can_search_black_lists_by_city(): void
    {
        $b1 = BlackList::create(['ip' => '10.10.20.1']);
        Visitor::create(['ip' => '10.10.20.1', 'city' => 'Chongqing']);

        $b2 = BlackList::create(['ip' => '10.10.20.2']);
        Visitor::create(['ip' => '10.10.20.2', 'city' => 'Harbin']);

        Livewire::actingAs($this->admin)
            ->test(ListBlackLists::class)
            ->searchTable('Chongqing')
            ->assertCanSeeTableRecords([$b1])
            ->assertCanNotSeeTableRecords([$b2]);
    }

    public function test_can_filter_black_lists_by_date_range(): void
    {
        $past = BlackList::create(['ip' => '192.0.2.11']);
        $past->created_at = now()->subDays(10);
        $past->save();

        $today = BlackList::create(['ip' => '192.0.2.22']);

        Livewire::actingAs($this->admin)
            ->test(ListBlackLists::class)
            ->filterTable('created_at', [
                'created_from' => now()->subDay()->toDateString(),
                'created_until' => now()->addDay()->toDateString(),
            ])
            ->assertCanSeeTableRecords([$today])
            ->assertCanNotSeeTableRecords([$past]);
    }

    public function test_authenticated_admin_can_create_black_list_with_valid_ip(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateBlackList::class)
            ->fillForm([
                'ip' => '198.51.100.55',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('black_lists', [
            'ip' => '198.51.100.55',
            'deleted_at' => null,
        ]);
    }

    public function test_cannot_create_black_list_with_invalid_ip(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateBlackList::class)
            ->fillForm([
                'ip' => '999.999.999.999',
            ])
            ->call('create')
            ->assertHasFormErrors(['ip' => 'ip']);
    }

    public function test_cannot_create_black_list_with_duplicate_ip(): void
    {
        BlackList::create(['ip' => '198.51.100.66']);

        Livewire::actingAs($this->admin)
            ->test(CreateBlackList::class)
            ->fillForm([
                'ip' => '198.51.100.66',
            ])
            ->call('create')
            ->assertHasFormErrors(['ip' => 'unique']);
    }

    public function test_authenticated_admin_can_edit_black_list(): void
    {
        $blackList = BlackList::create(['ip' => '198.51.100.77']);

        Livewire::actingAs($this->admin)
            ->test(EditBlackList::class, [
                'record' => $blackList->getKey(),
            ])
            ->fillForm([
                'ip' => '198.51.100.78',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('black_lists', [
            'id' => $blackList->id,
            'ip' => '198.51.100.78',
        ]);
    }

    public function test_authenticated_admin_can_delete_black_list(): void
    {
        $blackList = BlackList::create(['ip' => '198.51.100.88']);

        Livewire::actingAs($this->admin)
            ->test(ListBlackLists::class)
            ->searchTable('198.51.100.88')
            ->callTableAction('delete', $blackList);

        $this->assertSoftDeleted('black_lists', [
            'id' => $blackList->id,
        ]);
    }

    public function test_authenticated_admin_can_bulk_delete_black_lists(): void
    {
        $b1 = BlackList::create(['ip' => '198.51.100.91']);
        $b2 = BlackList::create(['ip' => '198.51.100.92']);

        Livewire::actingAs($this->admin)
            ->test(ListBlackLists::class)
            ->callTableBulkAction('delete', [$b1, $b2]);

        $this->assertSoftDeleted('black_lists', ['id' => $b1->id]);
        $this->assertSoftDeleted('black_lists', ['id' => $b2->id]);
    }

    public function test_view_logs_action_url_links_to_black_list_logs_filtered_by_ip(): void
    {
        $blackList = BlackList::create(['ip' => '198.51.100.99']);

        Livewire::actingAs($this->admin)
            ->test(ListBlackLists::class)
            ->assertTableActionExists('view_logs')
            ->assertTableActionHasUrl('view_logs', BlackListLogResource::getUrl('index', ['tableSearch' => '198.51.100.99']), $blackList);
    }
}
