<?php

namespace App\Console\Commands;

use App\Models\AdminOperationLog;
use Illuminate\Console\Command;

class PruneAdminOperationLogsCommand extends Command
{
    protected $signature = 'admin-operation-logs:prune {--days=365 : Retain at least this many days (30–36500)} {--force : Delete matching logs; otherwise only count them}';

    protected $description = 'Preview or explicitly prune old admin operation logs in bounded batches';

    public function handle(): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 30, 'max_range' => 36500]]);
        if ($days === false) {
            $this->error('--days must be an integer between 30 and 36500.');

            return self::FAILURE;
        }

        $model = new AdminOperationLog;
        $query = $model->getConnection()->table($model->getTable())->where('occurred_at', '<', now()->subDays($days));
        if (! $this->option('force')) {
            $this->info('Dry run: '.$query->count().' logs eligible. Use --force to delete.');

            return self::SUCCESS;
        }

        $deleted = 0;
        // Explicit maintenance bypasses the model's update/delete protections.
        while (($ids = (clone $query)->orderBy('id')->limit(1000)->pluck('id'))->isNotEmpty()) {
            $deleted += (clone $query)->whereIn('id', $ids)->delete();
        }
        $this->info('Deleted '.$deleted.' admin operation logs.');

        return self::SUCCESS;
    }
}
