<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class QueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // 最大失败次数
    public $tries = 5;

    // 超时
    public $timeout = 120;

    public $failOnTimeout = true;

    protected $count;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $count)
    {
        $this->queue = 'queue-test';
        $this->count = $count;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        for ($i = 0; $i < $this->count; $i++) {
            cache()->put('key:'.$i, 'value:'.$i, rand(600, 900));
        }
    }
}
