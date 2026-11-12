<?php

namespace App\Console\Commands;

use App\Models\Visitor;
use App\Models\VisitorLog;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
        $logQuery = VisitorLog::withTrashed()->where('created_at', '<', $cutoff);
        $visitorQuery = $this->orphanedVisitors($cutoff);

        if (! $this->option('force')) {
            $this->info('Dry run: '.$logQuery->count().' visitor logs and '.$visitorQuery->count().' visitors eligible. Use --force to delete.');

            return self::SUCCESS;
        }

        $logs = $this->deleteInBatches($logQuery, VisitorLog::class);
        $visitors = $this->deleteInBatches($visitorQuery, Visitor::class);
        $this->info("Deleted {$logs} visitor logs and {$visitors} visitors.");

        return self::SUCCESS;
    }

    private function retentionDays(): ?int
    {
        $option = $this->option('days');

        if ($option === null) {
            $option = (string) config('visitor.retention_days', 30);
        }

        $days = filter_var($option, FILTER_VALIDATE_INT, [
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

    /**
     * @param  class-string<Model>  $model
     */
    private function deleteInBatches(Builder $query, string $model): int
    {
        $deleted = 0;

        while (($ids = (clone $query)->orderBy('id')->limit(1000)->pluck('id'))->isNotEmpty()) {
            $deleted += $model::withTrashed()->whereIn('id', $ids)->forceDelete();
        }

        return $deleted;
    }
}
