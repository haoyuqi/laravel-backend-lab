<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\HealthStatusWidget;
use App\Filament\Widgets\ProjectInfoWidget;
use App\Filament\Widgets\StatsOverview;
use App\Models\BlackList;
use App\Models\User;
use App\Models\Visitor;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Livewire\Livewire;
use Tests\TestCase;

class StatsOverviewWidgetTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('stats_overview_visitors_count');
        Cache::forget('stats_overview_blacklist_count');

        $this->admin = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Cache::forget('stats_overview_visitors_count');
        Cache::forget('stats_overview_blacklist_count');
        Redis::clearResolvedInstances();

        $today = now()->toDateString();
        try {
            Redis::del('pv_count_'.$today, 'uv_set_'.$today);
        } catch (\Throwable) {
            // Ignore in case Redis is mocked or unreachable
        }

        parent::tearDown();
    }

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $response = $this->get('/filament');

        $response->assertRedirect('/filament/login');
    }

    public function test_stats_overview_widget_renders_successfully_for_admin(): void
    {
        Livewire::actingAs($this->admin)
            ->test(StatsOverview::class)
            ->assertSuccessful()
            ->assertSee('今日 PV')
            ->assertSee('今日 UV')
            ->assertSee('访客总数')
            ->assertSee('黑名单 IP');
    }

    public function test_stats_overview_widget_displays_pv_and_uv_from_redis(): void
    {
        $today = now()->toDateString();
        $pvKey = 'pv_count_'.$today;
        $uvKey = 'uv_set_'.$today;

        Redis::set($pvKey, 150);
        Redis::del($uvKey);
        Redis::sadd($uvKey, '10.0.0.1', '10.0.0.2', '10.0.0.3');

        Livewire::actingAs($this->admin)
            ->test(StatsOverview::class)
            ->assertSuccessful()
            ->assertSee('今日 PV')
            ->assertSee('150')
            ->assertSee('今日 UV')
            ->assertSee('3');
    }

    public function test_stats_overview_widget_displays_database_counts(): void
    {
        Visitor::create(['ip' => '172.16.0.10', 'city' => 'Beijing']);
        Visitor::create(['ip' => '172.16.0.11', 'city' => 'Shanghai']);
        Visitor::create(['ip' => '172.16.0.12', 'city' => 'Guangzhou']);

        BlackList::create(['ip' => '192.168.10.1']);
        $deletedBlacklist = BlackList::create(['ip' => '192.168.10.2']);
        $deletedBlacklist->delete();

        $expectedVisitors = Visitor::count();
        $expectedBlacklist = BlackList::count();

        Livewire::actingAs($this->admin)
            ->test(StatsOverview::class)
            ->assertSuccessful()
            ->assertSee('访客总数')
            ->assertSee(number_format($expectedVisitors))
            ->assertSee('黑名单 IP')
            ->assertSee(number_format($expectedBlacklist));
    }

    public function test_stats_overview_widget_defaults_to_zero_when_redis_empty(): void
    {
        $today = now()->toDateString();
        Redis::del('pv_count_'.$today);
        Redis::del('uv_set_'.$today);

        Livewire::actingAs($this->admin)
            ->test(StatsOverview::class)
            ->assertSuccessful()
            ->assertSee('今日 PV')
            ->assertSee('今日 UV')
            ->assertSee('0');
    }

    public function test_stats_overview_widget_handles_redis_failure_gracefully(): void
    {
        Redis::shouldReceive('get')
            ->andThrow(new Exception('Redis connection timed out'));

        Livewire::actingAs($this->admin)
            ->test(StatsOverview::class)
            ->assertSuccessful()
            ->assertSee('今日 PV')
            ->assertSee('0')
            ->assertSee('今日 UV')
            ->assertSee('0');
    }

    public function test_filament_dashboard_homepage_includes_stats_overview_widget(): void
    {
        $response = $this->actingAs($this->admin)->get('/filament');

        $response->assertSuccessful();
        $response->assertSeeLivewire(StatsOverview::class);
        $response->assertSeeLivewire(ProjectInfoWidget::class);
        $response->assertSeeLivewire(HealthStatusWidget::class);
        $response->assertSee('Laravel Backend Lab');
        $response->assertSee('GitHub 源码');
        $response->assertSee('v2.0.0');
    }

    public function test_health_status_widget_renders_successfully(): void
    {
        $driver = (string) config('database.default', 'pgsql');
        $expectedDb = match ($driver) {
            'pgsql' => 'PostgreSQL',
            'mysql' => 'MySQL',
            'sqlite' => 'SQLite',
            default => ucfirst($driver),
        };

        Livewire::actingAs($this->admin)
            ->test(HealthStatusWidget::class)
            ->assertSuccessful()
            ->assertSee('基础设施与服务健康')
            ->assertSee($expectedDb)
            ->assertSee('Asia/Shanghai')
            ->assertSee('服务正常');

        config(['cache.default' => 'redis']);
        config(['app.debug' => true]);

        Livewire::actingAs($this->admin)
            ->test(HealthStatusWidget::class)
            ->assertSuccessful()
            ->assertSee('Redis 缓存')
            ->assertSee('PHP 8.3')
            ->assertSee('Laravel 13')
            ->assertSee('Debug');

        config(['app.debug' => false]);

        Livewire::actingAs($this->admin)
            ->test(HealthStatusWidget::class)
            ->assertSuccessful()
            ->assertSee('Debug');
    }
}
