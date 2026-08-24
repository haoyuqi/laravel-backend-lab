<?php

namespace App\Jobs;

use App\Models\BlackList;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BlackListLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // 最大失败次数
    public $tries = 5;

    // 超时
    public $timeout = 120;

    protected $blackListIp;

    protected $url;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($black_list_ip, $url)
    {
        $this->queue = 'black-list-log';
        $this->blackListIp = $black_list_ip;
        $this->url = $url;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $black_list = BlackList::where('ip', $this->blackListIp)->first();

        if (! $black_list) {
            Log::error('black list log queue error.');
            Log::error('find '.$this->blackListIp.' error.');

            return;
        }

        $black_list_log = new \App\Models\BlackListLog;
        $black_list_log->url = $this->url;

        $black_list->logs()->save($black_list_log);
    }
}
