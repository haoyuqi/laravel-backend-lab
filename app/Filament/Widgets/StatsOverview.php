<?php

namespace App\Filament\Widgets;

use App\Models\BlackList;
use App\Models\Visitor;
use App\Models\VisitorStatistics;
use App\Support\RedisFailureLogger;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $pv = null;
        $uv = null;
        $redisAvailable = false;

        try {
            $date = now()->toDateString();
            $pv = (int) (Redis::get('pv_count_'.$date) ?: 0);
            $uv = (int) (Redis::scard('uv_set_'.$date) ?: 0);
            $redisAvailable = true;
        } catch (\Throwable $e) {
            $pv = null;
            $uv = null;
            RedisFailureLogger::report('Failed to retrieve PV/UV stats from Redis', [
                'error' => $e->getMessage(),
            ]);
        }

        $visitorsCount = (int) rescue(
            fn () => Cache::remember('stats_overview_visitors_count', 60, fn () => Visitor::count()),
            fn () => Visitor::count()
        );
        $blacklistCount = (int) rescue(
            fn () => Cache::remember('stats_overview_blacklist_count', 60, fn () => BlackList::count()),
            fn () => BlackList::count()
        );

        $sparklineMap = $this->getWeeklySparklines($pv, $uv);

        return [
            Stat::make('今日 PV', $pv === null ? '不可用' : number_format($pv))
                ->description($redisAvailable ? '今日页面访问量' : 'Redis 数据暂时不可用')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($sparklineMap['pv'])
                ->color('success'),

            Stat::make('今日 UV', $uv === null ? '不可用' : number_format($uv))
                ->description($redisAvailable ? '今日独立访客数' : 'Redis 数据暂时不可用')
                ->descriptionIcon('heroicon-m-user-group')
                ->chart($sparklineMap['uv'])
                ->color('info'),

            Stat::make('访客总数', number_format($visitorsCount))
                ->description('累计独立访客')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('黑名单 IP', number_format($blacklistCount))
                ->description('已被拦截 IP 数')
                ->descriptionIcon('heroicon-m-no-symbol')
                ->color('danger'),
        ];
    }

    /**
     * @return array{pv: array<int|null>, uv: array<int|null>}
     */
    protected function getWeeklySparklines(?int $todayPv, ?int $todayUv): array
    {
        try {
            /** @var Collection<string, Collection<int, VisitorStatistics>> $history */
            $history = VisitorStatistics::whereIn('type', ['pv', 'uv'])
                ->where('date', '>=', now()->subDays(6)->toDateString())
                ->where('date', '<=', now()->toDateString())
                ->orderBy('date')
                ->get()
                ->groupBy('type');

            $dates = collect(range(6, 0))->map(fn (int $daysAgo): string => now()->subDays($daysAgo)->toDateString());
            $today = now()->toDateString();

            return [
                'pv' => $this->buildSparklineSeries($history->get('pv'), $dates, $today, $todayPv),
                'uv' => $this->buildSparklineSeries($history->get('uv'), $dates, $today, $todayUv),
            ];
        } catch (\Throwable) {
            return [
                'pv' => [],
                'uv' => [],
            ];
        }
    }

    /**
     * @param  Collection<int, VisitorStatistics>|null  $records
     * @param  Collection<int, string>  $dates
     * @return array<int|null>
     */
    protected function buildSparklineSeries(?Collection $records, Collection $dates, string $today, ?int $todayValue): array
    {
        $countsByDate = $records?->keyBy(fn (VisitorStatistics $record): string => (string) $record->date);

        return $dates->map(function (string $date) use ($countsByDate, $today, $todayValue): ?int {
            if ($date === $today && $todayValue !== null) {
                return $todayValue;
            }

            $record = $countsByDate?->get($date);

            return $record ? (int) $record->count : null;
        })->all();
    }
}
