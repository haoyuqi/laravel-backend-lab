<?php

namespace App\Console\Commands;

use App\Support\RedisFailureLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class DeleteRedisCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete:redis-cache {key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete useless redis cache.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $key = $this->argument('key');

        try {
            if (! Redis::exists($key)) {
                $info = "`$key` does not exist.";
            } else {
                $isSuccess = Redis::del($key);
                $info = "`$key`".($isSuccess ? ' delete success.' : ' delete failed.');
            }
        } catch (\Throwable $e) {
            RedisFailureLogger::report('delete redis cache failure', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            $this->warn("Failed to delete redis cache for key `{$key}`: ".$e->getMessage());

            return Command::FAILURE;
        }

        info('Delete redis cache.');
        info($info);

        $this->info($info);

        return Command::SUCCESS;
    }
}
