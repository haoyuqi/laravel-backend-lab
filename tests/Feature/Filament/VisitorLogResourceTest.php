<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\VisitorLogResource;
use App\Filament\Resources\VisitorLogResource\Pages\ListVisitorLogs;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class VisitorLogResourceTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    public function test_unauthenticated_user_cannot_access_visitor_logs_index(): void
    {
        $response = $this->get(VisitorLogResource::getUrl('index'));

        $response->assertRedirect('/filament/login');
    }

    public function test_authenticated_admin_can_access_visitor_logs_index(): void
    {
        $response = $this->actingAs($this->admin)->get(VisitorLogResource::getUrl('index'));

        $response->assertSuccessful();
    }

    public function test_visitor_logs_table_can_render_records_with_visitor_ip(): void
    {
        $visitor = Visitor::create([
            'ip' => '192.168.1.101',
            'city' => 'Chengdu',
        ]);

        $log = new VisitorLog;
        $log->url = 'https://example.com/test-page';
        $visitor->logs()->save($log);

        Livewire::actingAs($this->admin)
            ->test(ListVisitorLogs::class)
            ->assertCanSeeTableRecords([$log])
            ->assertCanRenderTableColumn('visitor.ip')
            ->assertCanRenderTableColumn('url')
            ->assertCanRenderTableColumn('created_at');
    }

    public function test_can_search_visitor_logs_by_url(): void
    {
        $visitor = Visitor::create([
            'ip' => '192.168.1.102',
            'city' => 'Wuhan',
        ]);

        $log1 = new VisitorLog;
        $log1->url = 'https://example.com/unique-url-alpha';
        $visitor->logs()->save($log1);

        $log2 = new VisitorLog;
        $log2->url = 'https://example.com/unique-url-beta';
        $visitor->logs()->save($log2);

        Livewire::actingAs($this->admin)
            ->test(ListVisitorLogs::class)
            ->searchTable('unique-url-alpha')
            ->assertCanSeeTableRecords([$log1])
            ->assertCanNotSeeTableRecords([$log2]);
    }

    public function test_can_search_visitor_logs_by_visitor_ip(): void
    {
        $visitor1 = Visitor::create([
            'ip' => '198.51.100.1',
            'city' => 'Nanjing',
        ]);
        $log1 = new VisitorLog;
        $log1->url = 'https://example.com/page-1';
        $visitor1->logs()->save($log1);

        $visitor2 = Visitor::create([
            'ip' => '203.0.113.2',
            'city' => 'Suzhou',
        ]);
        $log2 = new VisitorLog;
        $log2->url = 'https://example.com/page-2';
        $visitor2->logs()->save($log2);

        Livewire::actingAs($this->admin)
            ->test(ListVisitorLogs::class)
            ->searchTable('198.51.100.1')
            ->assertCanSeeTableRecords([$log1])
            ->assertCanNotSeeTableRecords([$log2]);
    }

    public function test_visitor_log_resource_is_read_only(): void
    {
        $this->assertFalse(VisitorLogResource::canCreate());
        $this->assertFalse(VisitorLogResource::canEdit(new VisitorLog));
        $this->assertFalse(VisitorLogResource::canDelete(new VisitorLog));
        $this->assertFalse(VisitorLogResource::canDeleteAny());
    }
}
