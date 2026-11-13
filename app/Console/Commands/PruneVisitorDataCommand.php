<?php

namespace App\Console\Commands;

use App\Models\Visitor;
use App\Models\VisitorLog;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PruneVisitorDataCommand extends Command
{
    protected $signature = 'visitor-data:prune
        {--days= : Retain at least this many days (1–3650); defaults to visitor.retention_days}
        {--force : Delete matching records; otherwise only count them}';

    protected $description = 'Preview or permanently prune raw visitor records older than the retention window';

    public function handle(): int
    {
        $days = $this->retentionDays();

        if ($days === null) {
            $this->error('--days must be an integer between 1 and 3650.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);

        if (! $this->option('force')) {
            $logCount = VisitorLog::withTrashed()->where('created_at', '<', $cutoff)->count();
            $visitorCount = $this->orphanedVisitors($cutoff)->count();
            $this->info("Dry run: {$logCount} visitor logs and {$visitorCount} visitors eligible. Use --force to delete.");

            return self::SUCCESS;
        }

        $logs = $this->deleteExpiredLogs($cutoff);
        $visitors = $this->deleteOrphanedVisitors($cutoff);
        $this->info("Deleted {$logs} visitor logs and {$visitors} visitors.");

        return self::SUCCESS;
    }

    private function retentionDays(): ?int
    {
        $raw = $this->option('days') ?? config('visitor.retention_days', 30);

        if (! is_int($raw) && ! is_string($raw)) {
            return null;
        }

        $days = filter_var($raw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 3650],
        ]);

        return $days === false ? null : $days;
    }

    /**
     * Visitors whose newest retained log is already expired. Their request
     * history no longer justifies keeping the raw IP address.
     */
    private function orphanedVisitors(\DateTimeInterface $cutoff): Builder
    {
        return Visitor::withTrashed()
            ->where('created_at', '<', $cutoff)
            ->whereDoesntHave('logs', function (Builder $query) use ($cutoff): void {
                $query->withTrashed()->where('created_at', '>=', $cutoff);
            });
    }

    private function deleteExpiredLogs(\DateTimeInterface $cutoff): int
    {
        $deleted = 0;
        $lastId = 0;

        while (true) {
            $ids = VisitorLog::withTrashed()
                ->where('created_at', '<', $cutoff)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(1000)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $lastId = (int) $ids->last();
            $deleted += DB::transaction(
                fn (): int => VisitorLog::withTrashed()->whereIn('id', $ids)->forceDelete(),
                3
            );
        }

        return $deleted;
    }

    private function deleteOrphanedVisitors(\DateTimeInterface $cutoff): int
    {
        $deleted = 0;
        $lastId = 0;

        while (true) {
            $ids = $this->orphanedVisitors($cutoff)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(1000)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $lastId = (int) $ids->last();

            $deleted += DB::transaction(function () use ($ids, $cutoff): int {
                $lockedIds = Visitor::withTrashed()
                    ->whereIn('id', $ids)
                    ->where('created_at', '<', $cutoff)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->pluck('id');

                $activeIds = VisitorLog::withTrashed()
                    ->whereIn('visitor_id', $lockedIds)
                    ->where('created_at', '>=', $cutoff)
                    ->lockForUpdate()
                    ->pluck('visitor_id')
                    ->all();

                $deletableIds = array_values(array_diff($lockedIds->all(), $activeIds));

                if ($deletableIds === []) {
                    return 0;
                }

                return Visitor::withTrashed()->whereIn('id', $deletableIds)->forceDelete();
            }, 3);
        }

        return $deleted;
    }
}
