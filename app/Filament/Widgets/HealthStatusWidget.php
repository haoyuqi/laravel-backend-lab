<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthStatusWidget extends Widget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.widgets.health-status-widget';

    public function getViewData(): array
    {
        $database = $this->checkDatabaseHealth();
        $cache = $this->checkCacheHealth();
        $timezone = $this->getTimezoneStatus();
        $runtime = $this->getRuntimeStatus();

        $allHealthy = ($database['status'] === 'healthy') && ($cache['status'] === 'healthy');

        $cards = [
            [
                'icon' => 'heroicon-m-circle-stack',
                'title' => $database['name'],
                'subtitle' => '驱动: '.$database['driver'],
                'badge_text' => $database['latency'],
                'badge_color' => $database['status'] === 'healthy' ? 'success' : 'danger',
                'badge_icon' => $database['status'] === 'healthy' ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle',
            ],
            [
                'icon' => 'heroicon-m-bolt',
                'title' => $cache['name'].' 缓存',
                'subtitle' => '驱动: '.$cache['driver'],
                'badge_text' => $cache['latency'],
                'badge_color' => $cache['status'] === 'healthy' ? 'success' : 'danger',
                'badge_icon' => $cache['status'] === 'healthy' ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle',
            ],
            [
                'icon' => 'heroicon-m-clock',
                'title' => $timezone['name'],
                'subtitle' => '时间: '.$timezone['time'],
                'badge_text' => $timezone['offset'],
                'badge_color' => 'success',
                'badge_icon' => 'heroicon-m-check-circle',
            ],
            [
                'icon' => 'heroicon-m-cpu-chip',
                'title' => $runtime['php'],
                'subtitle' => $runtime['laravel'],
                'badge_text' => 'Debug',
                'badge_color' => $runtime['debug'] ? 'success' : 'gray',
                'badge_icon' => $runtime['debug'] ? 'heroicon-m-check-circle' : 'heroicon-m-minus-circle',
            ],
        ];

        return [
            'isHealthy' => $allHealthy,
            'cards' => $cards,
        ];
    }

    protected function checkDatabaseHealth(): array
    {
        try {
            $driver = (string) DB::connection()->getDriverName();
        } catch (\Throwable) {
            $driver = (string) config('database.default', 'pgsql');
        }

        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $latency = round((microtime(true) - $start) * 1000, 1);

            return [
                'status' => 'healthy',
                'name' => match ($driver) {
                    'pgsql' => 'PostgreSQL',
                    'mysql' => 'MySQL',
                    'sqlite' => 'SQLite',
                    default => ucfirst($driver),
                },
                'driver' => $driver,
                'latency' => $latency.'ms',
                'label' => '连接正常',
            ];
        } catch (\Throwable) {
            return [
                'status' => 'unhealthy',
                'name' => match ($driver) {
                    'pgsql' => 'PostgreSQL',
                    'mysql' => 'MySQL',
                    'sqlite' => 'SQLite',
                    default => ucfirst($driver),
                },
                'driver' => $driver,
                'latency' => '--',
                'label' => '连接异常',
            ];
        }
    }

    protected function checkCacheHealth(): array
    {
        $driver = (string) config('cache.default', 'redis');

        try {
            $start = microtime(true);
            if ($driver === 'redis') {
                Redis::ping();
            }
            $latency = round((microtime(true) - $start) * 1000, 1);

            return [
                'status' => 'healthy',
                'name' => ucfirst($driver),
                'driver' => $driver,
                'latency' => $latency.'ms',
                'label' => '运行正常',
            ];
        } catch (\Throwable) {
            return [
                'status' => 'unhealthy',
                'name' => ucfirst($driver),
                'driver' => $driver,
                'latency' => '--',
                'label' => '服务异常',
            ];
        }
    }

    protected function getTimezoneStatus(): array
    {
        $tz = (string) config('app.timezone', 'UTC');
        $now = now();
        $offset = 'UTC'.($now->offsetHours >= 0 ? '+' : '').$now->offsetHours;

        return [
            'status' => 'healthy',
            'name' => $tz,
            'time' => $now->format('H:i:s'),
            'offset' => $offset,
        ];
    }

    protected function getRuntimeStatus(): array
    {
        $isDebug = (bool) config('app.debug', false);
        $laravelMajor = explode('.', (string) app()->version())[0];

        return [
            'status' => 'healthy',
            'php' => 'PHP '.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
            'laravel' => 'Laravel '.$laravelMajor,
            'debug' => $isDebug,
        ];
    }
}
