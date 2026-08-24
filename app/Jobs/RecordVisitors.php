<?php

namespace App\Jobs;

use App\Libraries\GetCityByIp\GetCityByIpAbstract;
use App\Models\Visitor;
use App\Models\VisitorLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecordVisitors implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // 最大失败次数
    public $tries = 5;

    // 超时
    public $timeout = 120;

    protected $ip;

    protected $url;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($ip, $url)
    {
        $this->queue = 'record-visitor';
        $this->ip = $ip;
        $this->url = $url;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(GetCityByIpAbstract $getCityByIp)
    {
        $visitor = Visitor::firstOrCreate(
            ['ip' => $this->ip],
            ['city' => $getCityByIp->getCity($this->ip)]
        );

        $visitor_log = new VisitorLog;
        $visitor_log->url = $this->url;

        $visitor->logs()->save($visitor_log);
    }
}
