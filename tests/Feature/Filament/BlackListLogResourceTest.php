<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\BlackListLogResource;
use App\Filament\Resources\BlackListLogResource\Pages\ListBlackListLogs;
use App\Models\BlackList;
use App\Models\BlackListLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class BlackListLogResourceTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    public function test_unauthenticated_user_cannot_access_black_list_logs_index(): void
    {
        $response = $this->get(BlackListLogResource::getUrl('index'));

        $response->assertRedirect('/filament/login');
    }

    public function test_authenticated_admin_can_access_black_list_logs_index(): void
    {
        $response = $this->actingAs($this->admin)->get(BlackListLogResource::getUrl('index'));

        $response->assertSuccessful();
    }

    public function test_black_list_logs_table_can_render_records_with_black_list_ip(): void
    {
        $blackList = BlackList::create(['ip' => '192.168.30.1']);
        $log = new BlackListLog;
        $log->url = 'https://example.com/blocked-url-1';
        $blackList->logs()->save($log);

        Livewire::actingAs($this->admin)
            ->test(ListBlackListLogs::class)
            ->searchTable('192.168.30.1')
            ->assertCanSeeTableRecords([$log])
            ->assertCanRenderTableColumn('blackList.ip')
            ->assertCanRenderTableColumn('url')
            ->assertCanRenderTableColumn('created_at');
    }

    public function test_can_search_black_list_logs_by_url(): void
    {
        $blackList = BlackList::create(['ip' => '192.168.30.2']);

        $log1 = new BlackListLog;
        $log1->url = 'https://example.com/unique-blocked-url-apple';
        $blackList->logs()->save($log1);

        $log2 = new BlackListLog;
        $log2->url = 'https://example.com/unique-blocked-url-banana';
        $blackList->logs()->save($log2);

        Livewire::actingAs($this->admin)
            ->test(ListBlackListLogs::class)
            ->searchTable('unique-blocked-url-apple')
            ->assertCanSeeTableRecords([$log1])
            ->assertCanNotSeeTableRecords([$log2]);
    }

    public function test_can_search_black_list_logs_by_black_list_ip(): void
    {
        $b1 = BlackList::create(['ip' => '192.168.30.11']);
        $log1 = new BlackListLog;
        $log1->url = 'https://example.com/path-1';
        $b1->logs()->save($log1);

        $b2 = BlackList::create(['ip' => '192.168.30.22']);
        $log2 = new BlackListLog;
        $log2->url = 'https://example.com/path-2';
        $b2->logs()->save($log2);

        Livewire::actingAs($this->admin)
            ->test(ListBlackListLogs::class)
            ->searchTable('192.168.30.11')
            ->assertCanSeeTableRecords([$log1])
            ->assertCanNotSeeTableRecords([$log2]);
    }

    public function test_can_filter_black_list_logs_by_date_range(): void
    {
        $blackList = BlackList::create(['ip' => '192.168.30.33']);

        $logPast = new BlackListLog;
        $logPast->url = 'https://example.com/past-blocked';
        $blackList->logs()->save($logPast);
        $logPast->created_at = now()->subDays(10);
        $logPast->save();

        $logToday = new BlackListLog;
        $logToday->url = 'https://example.com/today-blocked';
        $blackList->logs()->save($logToday);

        Livewire::actingAs($this->admin)
            ->test(ListBlackListLogs::class)
            ->filterTable('created_at', [
                'created_from' => now()->subDay()->toDateString(),
                'created_until' => now()->addDay()->toDateString(),
            ])
            ->assertCanSeeTableRecords([$logToday])
            ->assertCanNotSeeTableRecords([$logPast]);
    }

    public function test_black_list_logs_table_renders_ip_even_if_black_list_is_soft_deleted(): void
    {
        $blackList = BlackList::create(['ip' => '192.168.30.99']);
        $log = new BlackListLog;
        $log->url = 'https://example.com/blocked-url-deleted-parent';
        $blackList->logs()->save($log);

        $blackList->delete();
        $this->assertSoftDeleted('black_lists', ['id' => $blackList->id]);

        Livewire::actingAs($this->admin)
            ->test(ListBlackListLogs::class)
            ->searchTable('192.168.30.99')
            ->assertCanSeeTableRecords([$log])
            ->assertCanRenderTableColumn('blackList.ip');
    }

    public function test_black_list_log_resource_is_read_only(): void
    {
        $this->assertFalse(BlackListLogResource::canCreate());
        $this->assertFalse(BlackListLogResource::canEdit(new BlackListLog));
        $this->assertFalse(BlackListLogResource::canDelete(new BlackListLog));
        $this->assertFalse(BlackListLogResource::canDeleteAny());
    }
}
