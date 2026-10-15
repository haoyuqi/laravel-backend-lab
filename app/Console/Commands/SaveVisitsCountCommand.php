<?php

namespace App\Console\Commands;

use App\Models\VisitorStatistics;
use App\Support\RedisFailureLogger;
use App\Support\VisitorStatisticsRetention;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class SaveVisitsCountCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'save:visits-count {--date= : Statistics date (YYYY-MM-DD), within the retained retry window}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Store PV and UV data into database.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $today = CarbonImmutable::now()->startOfDay();
        $dateInput = $this->option('date');
        $statisticsDate = $today->subDay();
        if ($dateInput !== null) {
            try {
                if (! is_string($dateInput) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $dateInput) !== 1) {
                    throw new \InvalidArgumentException;
                }
                $statisticsDate = CarbonImmutable::createFromFormat('!Y-m-d', $dateInput, $today->timezone);
                if ($statisticsDate->toDateString() !== $dateInput) {
                    throw new \InvalidArgumentException;
                }
            } catch (\Throwable) {
                $this->warn('Invalid statistics date; use YYYY-MM-DD.');

                return Command::FAILURE;
            }
        }
        if ($statisticsDate->greaterThanOrEqualTo($today) || now()->getTimestamp() >= VisitorStatisticsRetention::expiresAt($statisticsDate)) {
            $this->warn('Statistics date must be a completed day within the retained retry window.');

            return Command::FAILURE;
        }
        $dateStr = $statisticsDate->toDateString();

        try {
            $uvCount = (int) Redis::scard('uv_set_'.$dateStr);
            $pvCount = Redis::get('pv_count_'.$dateStr);

            // Redis removes empty sets; a zero UV count means a missing snapshot.
            if ($uvCount === 0 || filter_var($pvCount, FILTER_VALIDATE_INT) === false || (int) $pvCount < 0) {
                $this->warn('Visits count is incomplete; database statistics were left unchanged.');

                return Command::FAILURE;
            }

            $pvCount = (int) $pvCount;
        } catch (\Throwable $e) {
            RedisFailureLogger::report('failed to retrieve visits count from Redis', [
                'date' => $dateStr,
                'error' => $e->getMessage(),
            ]);

            $this->warn('Failed to retrieve visits count from Redis: '.$e->getMessage());

            return Command::FAILURE;
        }

        try {
            DB::transaction(function () use ($dateStr, $uvCount, $pvCount): void {
                // Create lockable rows even on the first run. Stable ordering avoids
                // missing-row races and serializes both halves of concurrent retries.
                $timestamp = now();
                DB::table('visitor_statistics')->insertOrIgnore([
                    ['type' => 'pv', 'date' => $dateStr, 'count' => 0, 'created_at' => $timestamp, 'updated_at' => $timestamp],
                    ['type' => 'uv', 'date' => $dateStr, 'count' => 0, 'created_at' => $timestamp, 'updated_at' => $timestamp],
                ]);
                $existing = VisitorStatistics::withTrashed()->where('date', $dateStr)
                    ->whereIn('type', ['pv', 'uv'])->orderBy('type')->lockForUpdate()->get();
                foreach ($existing as $statistic) {
                    $incoming = $statistic->type === 'pv' ? $pvCount : $uvCount;
                    if ($incoming < (int) $statistic->count) {
                        throw new \UnexpectedValueException('Redis snapshot is lower than saved statistics; neither PV nor UV was updated.');
                    }
                }
                $this->saveStatistic('pv', $dateStr, $pvCount);
                $this->saveStatistic('uv', $dateStr, $uvCount);
            }, 3);
        } catch (\UnexpectedValueException $e) {
            $this->warn($e->getMessage());

            return Command::FAILURE;
        }

        // Keep the snapshot available for retries, without extending its lifetime.
        try {
            Redis::expireat('uv_set_'.$dateStr, VisitorStatisticsRetention::expiresAt($statisticsDate));
            Redis::expireat('pv_count_'.$dateStr, VisitorStatisticsRetention::expiresAt($statisticsDate));
        } catch (\Throwable $e) {
            RedisFailureLogger::report('failed to expire visits count in Redis', [
                'date' => $dateStr,
                'error' => $e->getMessage(),
            ]);
            $this->warn('Statistics were saved, but Redis expiration failed. Retry the command.');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * The unique type/date key makes concurrent retries safe, including trashed rows.
     */
    protected function saveStatistic(string $type, string $date, int $count): void
    {
        VisitorStatistics::upsert([
            ['type' => $type, 'date' => $date, 'count' => $count, 'deleted_at' => null],
        ], ['type', 'date'], ['count', 'deleted_at', 'updated_at']);
    }
}
