<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\HealthStatusWidget;
use App\Filament\Widgets\ProjectInfoWidget;
use App\Filament\Widgets\StatsOverview;
use App\Models\BlackList;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorStatistics;
use App\Support\RedisFailureLogger;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Livewire\Livewire;
use Tests\TestCase;

class StatsOverviewWidgetTest extends TestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        RedisFailureLogger::reset();
        Cache::forget('stats_overview_visitors_count');
        Cache::forget('stats_overview_blacklist_count');

        $this->admin = User::factory()->create();
    }

    protected function tearDown(): void
    {
        RedisFailureLogger::reset();
        Cache::clearResolvedInstances();
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
        $response = $this->get('/admin');

        $response->assertRedirect('/admin/login');
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
        Log::shouldReceive('warning')
            ->once()
            ->with('Failed to retrieve PV/UV stats from Redis', \Mockery::on(fn (array $context): bool => str_contains($context['error'], 'Redis connection timed out')
            ));

        Redis::shouldReceive('get')
            ->andThrow(new Exception('Redis connection timed out'));

        Livewire::actingAs($this->admin)
            ->test(StatsOverview::class)
            ->assertSuccessful()
            ->assertSee('今日 PV')
            ->assertSee('不可用')
            ->assertSee('今日 UV')
            ->assertSee('Redis 数据暂时不可用');
    }

    public function test_weekly_sparklines_use_recorded_values_and_keep_missing_dates_unknown(): void
    {
        $today = now()->toDateString();
        $records = collect([
            new VisitorStatistics(['date' => now()->subDays(5)->toDateString(), 'count' => 12]),
            new VisitorStatistics(['date' => now()->subDays(2)->toDateString(), 'count' => 31]),
        ]);
        $dates = collect(range(6, 0))->map(fn (int $daysAgo): string => now()->subDays($daysAgo)->toDateString());
        $method = new \ReflectionMethod(StatsOverview::class, 'buildSparklineSeries');

        $series = $method->invoke(new StatsOverview, $records, $dates, $today, 44);

        $this->assertSame([null, 12, null, null, 31, null, 44], $series);
    }

    public function test_weekly_sparklines_degrade_to_empty_series_when_storage_is_unavailable(): void
    {
        $connection = 'missing_statistics_table';
        $previousConnection = config('database.default');
        config([
            'database.default' => $connection,
            'database.connections.'.$connection => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge($connection);

        try {
            $method = new \ReflectionMethod(StatsOverview::class, 'getWeeklySparklines');

            $this->assertSame(['pv' => [], 'uv' => []], $method->invoke(new StatsOverview, null, null));
        } finally {
            DB::disconnect($connection);
            DB::purge($connection);
            config(['database.default' => $previousConnection]);
        }
    }

    public function test_health_widget_reports_cache_store_write_failure(): void
    {
        $store = 'health_check_failure';
        $databaseConnection = 'health_check_database';
        $previousStore = config('cache.default');
        config([
            'cache.default' => $store,
            'database.connections.'.$databaseConnection => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'cache.stores.'.$store => [
                'driver' => 'database',
                'table' => 'health_check_failure_cache',
                'connection' => $databaseConnection,
            ],
        ]);
        DB::purge($databaseConnection);
        Cache::clearResolvedInstances();

        try {
            Livewire::actingAs($this->admin)
                ->test(HealthStatusWidget::class)
                ->assertSuccessful()
                ->assertSee('注意检查')
                ->assertSee('Database 缓存');
        } finally {
            Cache::clearResolvedInstances();
            DB::disconnect($databaseConnection);
            DB::purge($databaseConnection);
            config(['cache.default' => $previousStore]);
        }
    }

    public function test_stats_overview_widget_handles_cache_failure_gracefully(): void
    {
        Visitor::create(['ip' => '172.16.0.20', 'city' => 'Shenzhen']);
        BlackList::create(['ip' => '192.168.20.1']);

        Cache::shouldReceive('forget')->byDefault();
        Cache::shouldReceive('remember')
            ->andThrow(new Exception('Redis cache connection refused'));

        Livewire::actingAs($this->admin)
            ->test(StatsOverview::class)
            ->assertSuccessful()
            ->assertSee('访客总数')
            ->assertSee('1')
            ->assertSee('黑名单 IP')
            ->assertSee('1');
    }

    public function test_filament_dashboard_homepage_includes_stats_overview_widget(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin');

        $response->assertSuccessful();
        $response->assertSeeLivewire(StatsOverview::class);
        $response->assertSeeLivewire(ProjectInfoWidget::class);
        $response->assertSeeLivewire(HealthStatusWidget::class);
        $response->assertSee('Laravel Backend Lab');
        $response->assertSee('GitHub 源码');
        $response->assertSee('v'.config('app.version'));
    }

    public function test_health_status_widget_renders_successfully(): void
    {
        // Match the effective PDO driver (what HealthStatusWidget reports),
        // not the connection name — under the test suite this is the
        // `testing` connection whose driver may be sqlite/mysql/pgsql.
        $driver = (string) DB::connection()->getDriverName();
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
            ->assertSee('PHP '.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION)
            ->assertSee('Laravel 13')
            ->assertSee('Debug');

        config(['app.debug' => false]);

        Livewire::actingAs($this->admin)
            ->test(HealthStatusWidget::class)
            ->assertSuccessful()
            ->assertSee('Debug');
    }

    public function test_health_widget_identifies_mysql_and_postgres_connections(): void
    {
        $connection = \Mockery::mock();
        $connection->shouldReceive('getDriverName')->twice()->andReturn('mysql', 'pgsql');
        DB::shouldReceive('connection')->twice()->andReturn($connection);
        DB::shouldReceive('select')->twice()->with('SELECT 1')->andReturn([]);
        $method = new \ReflectionMethod(HealthStatusWidget::class, 'checkDatabaseHealth');
        $widget = new HealthStatusWidget;

        $this->assertSame('MySQL', $method->invoke($widget)['name']);
        $this->assertSame('PostgreSQL', $method->invoke($widget)['name']);
    }

    public function test_health_widget_reports_the_configured_database_when_connection_fails(): void
    {
        config(['database.default' => 'pgsql']);
        DB::shouldReceive('connection')->once()->andThrow(new Exception('Connection unavailable'));
        DB::shouldReceive('select')->once()->with('SELECT 1')->andThrow(new Exception('Connection unavailable'));
        $method = new \ReflectionMethod(HealthStatusWidget::class, 'checkDatabaseHealth');

        $status = $method->invoke(new HealthStatusWidget);

        $this->assertSame('unhealthy', $status['status']);
        $this->assertSame('PostgreSQL', $status['name']);
        $this->assertSame('pgsql', $status['driver']);
        $this->assertSame('--', $status['latency']);
    }
}
