<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\VisitorLogResource;
use App\Filament\Resources\VisitorResource;
use App\Filament\Resources\VisitorResource\Pages\ListVisitors;
use App\Models\User;
use App\Models\Visitor;
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

    public function test_visitors_table_can_render_records(): void
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
            ->assertCanRenderTableColumn('created_at');
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
