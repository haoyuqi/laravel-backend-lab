<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\VisitorLogResource;
use App\Filament\Resources\VisitorResource;
use App\Filament\Resources\VisitorResource\Pages\ListVisitors;
use App\Models\BlackList;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class VisitorResourceTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    public function test_unauthenticated_user_cannot_access_visitors_index(): void
    {
        $response = $this->get(VisitorResource::getUrl('index'));

        $response->assertRedirect('/filament/login');
    }

    public function test_authenticated_admin_can_access_visitors_index(): void
    {
        $response = $this->actingAs($this->admin)->get(VisitorResource::getUrl('index'));

        $response->assertSuccessful();
    }

    public function test_visitors_table_can_render_records_with_all_columns(): void
    {
        $visitor = Visitor::create([
            'ip' => '192.168.1.100',
            'city' => 'Beijing',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListVisitors::class)
            ->assertCanSeeTableRecords([$visitor])
            ->assertCanRenderTableColumn('ip')
            ->assertCanRenderTableColumn('city')
            ->assertCanRenderTableColumn('today_logs_count')
            ->assertCanRenderTableColumn('all_logs_count')
            ->assertCanRenderTableColumn('is_blacklisted')
            ->assertCanRenderTableColumn('created_at')
            ->assertCanRenderTableColumn('updated_at');
    }

    public function test_today_and_all_logs_counts_are_calculated_correctly(): void
    {
        $visitor = Visitor::create([
            'ip' => '192.168.1.150',
            'city' => 'Nanjing',
        ]);

        $todayLog = new VisitorLog;
        $todayLog->url = 'https://example.com/today';
        $todayLog->created_at = now();
        $visitor->logs()->save($todayLog);

        $pastLog = new VisitorLog;
        $pastLog->url = 'https://example.com/past';
        $visitor->logs()->save($pastLog);
        $pastLog->created_at = now()->subDays(5);
        $pastLog->save();

        Livewire::actingAs($this->admin)
            ->test(ListVisitors::class)
            ->searchTable('192.168.1.150')
            ->assertCanSeeTableRecords([$visitor]);

        $reloaded = VisitorResource::getEloquentQuery()->find($visitor->id);
        $this->assertEquals(1, $reloaded->today_logs_count);
        $this->assertEquals(2, $reloaded->all_logs_count);
    }

    public function test_can_search_visitors_by_ip(): void
    {
        $visitor1 = Visitor::create([
            'ip' => '10.0.0.1',
            'city' => 'Shanghai',
        ]);

        $visitor2 = Visitor::create([
            'ip' => '172.16.0.1',
            'city' => 'Shenzhen',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListVisitors::class)
            ->searchTable('10.0.0.1')
            ->assertCanSeeTableRecords([$visitor1])
            ->assertCanNotSeeTableRecords([$visitor2]);
    }

    public function test_can_filter_visitors_by_date_range(): void
    {
        $visitorPast = Visitor::create([
            'ip' => '1.1.1.1',
            'city' => 'Guangzhou',
        ]);
        $visitorPast->created_at = now()->subDays(10);
        $visitorPast->save();

        $visitorToday = Visitor::create([
            'ip' => '2.2.2.2',
            'city' => 'Hangzhou',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListVisitors::class)
            ->filterTable('created_at', [
                'created_from' => now()->subDay()->toDateString(),
                'created_until' => now()->addDay()->toDateString(),
            ])
            ->assertCanSeeTableRecords([$visitorToday])
            ->assertCanNotSeeTableRecords([$visitorPast]);
    }

    public function test_can_filter_visitors_by_blacklist_status(): void
    {
        $blacklisted = Visitor::create([
            'ip' => '192.0.2.1',
            'city' => 'CityA',
        ]);
        BlackList::create(['ip' => '192.0.2.1']);

        $normal = Visitor::create([
            'ip' => '192.0.2.2',
            'city' => 'CityB',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListVisitors::class)
            ->filterTable('black_list', true)
            ->assertCanSeeTableRecords([$blacklisted])
            ->assertCanNotSeeTableRecords([$normal]);

        Livewire::actingAs($this->admin)
            ->test(ListVisitors::class)
            ->filterTable('black_list', false)
            ->assertCanSeeTableRecords([$normal])
            ->assertCanNotSeeTableRecords([$blacklisted]);
    }

    public function test_can_bulk_add_visitors_to_black_list(): void
    {
        $visitor = Visitor::create([
            'ip' => '198.51.100.99',
            'city' => 'Chengdu',
        ]);

        $this->assertDatabaseMissing('black_lists', [
            'ip' => '198.51.100.99',
            'deleted_at' => null,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListVisitors::class)
            ->callTableBulkAction('add_to_black_list', [$visitor])
            ->assertHasNoTableBulkActionErrors();

        $this->assertDatabaseHas('black_lists', [
            'ip' => '198.51.100.99',
            'deleted_at' => null,
        ]);
    }

    public function test_bulk_add_to_black_list_restores_soft_deleted_record(): void
    {
        $visitor = Visitor::create([
            'ip' => '203.0.113.88',
            'city' => 'Wuhan',
        ]);

        $deletedBlackList = BlackList::create(['ip' => '203.0.113.88']);
        $deletedBlackList->delete();

        $this->assertSoftDeleted('black_lists', ['ip' => '203.0.113.88']);

        Livewire::actingAs($this->admin)
            ->test(ListVisitors::class)
            ->callTableBulkAction('add_to_black_list', [$visitor])
            ->assertHasNoTableBulkActionErrors();

        $this->assertDatabaseHas('black_lists', [
            'ip' => '203.0.113.88',
            'deleted_at' => null,
        ]);
    }

    public function test_view_logs_action_url_links_to_visitor_logs_filtered_by_ip(): void
    {
        $visitor = Visitor::create([
            'ip' => '192.168.1.200',
            'city' => 'Tianjin',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListVisitors::class)
            ->assertTableActionExists('view_logs')
            ->assertTableActionHasUrl('view_logs', VisitorLogResource::getUrl('index', ['tableSearch' => '192.168.1.200']), $visitor);
    }

    public function test_visitor_resource_is_read_only(): void
    {
        $this->assertFalse(VisitorResource::canCreate());
        $this->assertFalse(VisitorResource::canEdit(new Visitor));
        $this->assertFalse(VisitorResource::canDelete(new Visitor));
        $this->assertFalse(VisitorResource::canDeleteAny());
    }
}
