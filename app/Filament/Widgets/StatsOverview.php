<?php

namespace App\Filament\Widgets;

use App\Models\BlackList;
use App\Models\Visitor;
use App\Models\VisitorStatistics;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $pv = 0;
        $uv = 0;

        try {
            $date = now()->toDateString();
            $pv = (int) (Redis::get('pv_count_'.$date) ?: 0);
            $uv = (int) (Redis::scard('uv_set_'.$date) ?: 0);
        } catch (\Throwable $e) {
            Log::warning('Failed to retrieve PV/UV stats from Redis: '.$e->getMessage());
        }

        $visitorsCount = (int) Cache::remember('stats_overview_visitors_count', 60, fn () => Visitor::count());
        $blacklistCount = (int) Cache::remember('stats_overview_blacklist_count', 60, fn () => BlackList::count());

        $sparklineMap = $this->getWeeklySparklines($pv, $uv);

        return [
            Stat::make('今日 PV', number_format($pv))
                ->description('今日页面访问量')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($sparklineMap['pv'])
                ->color('success'),

            Stat::make('今日 UV', number_format($uv))
                ->description('今日独立访客数')
                ->descriptionIcon('heroicon-m-user-group')
                ->chart($sparklineMap['uv'])
                ->color('info'),

            Stat::make('访客总数', number_format($visitorsCount))
                ->description('累计独立访客')
                ->descriptionIcon('heroicon-m-users')
                ->chart([max(0, $visitorsCount - 3), max(0, $visitorsCount - 2), max(0, $visitorsCount - 1), $visitorsCount])
                ->color('primary'),

            Stat::make('黑名单 IP', number_format($blacklistCount))
                ->description('已被拦截 IP 数')
                ->descriptionIcon('heroicon-m-no-symbol')
                ->chart([max(0, $blacklistCount - 2), max(0, $blacklistCount - 1), $blacklistCount])
                ->color('danger'),
        ];
    }

    /**
     * @return array{pv: array<int>, uv: array<int>}
     */
    protected function getWeeklySparklines(int $todayPv, int $todayUv): array
    {
        try {
            /** @var Collection<string, Collection<int, VisitorStatistics>> $history */
            $history = VisitorStatistics::whereIn('type', ['pv', 'uv'])
                ->where('date', '>=', now()->subDays(6)->toDateString())
                ->orderBy('date')
                ->get()
                ->groupBy('type');

            return [
                'pv' => $this->buildSparklineSeries($history->get('pv'), $todayPv),
                'uv' => $this->buildSparklineSeries($history->get('uv'), $todayUv),
            ];
        } catch (\Throwable) {
            return [
                'pv' => [$todayPv],
                'uv' => [$todayUv],
            ];
        }
    }

    /**
     * @param  Collection<int, VisitorStatistics>|null  $records
     * @return array<int>
     */
    protected function buildSparklineSeries(?Collection $records, int $todayValue): array
    {
        $series = $records ? $records->pluck('count')->all() : [];
        $series[] = $todayValue;

        if (count($series) < 2) {
            return [$todayValue > 0 ? (int) ($todayValue * 0.6) : 0, $todayValue];
        }

        return array_slice($series, -7);
    }
}
