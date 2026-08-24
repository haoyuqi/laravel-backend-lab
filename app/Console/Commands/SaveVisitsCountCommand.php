<?php

namespace App\Console\Commands;

use App\Models\VisitorStatistics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class SaveVisitsCountCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'save:visits-count';

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
        $yesterday = now()->subDay();

        $uv = new VisitorStatistics;
        $uv->type = 'uv';
        $uv->date = $yesterday;
        $uv->count = Redis::scard('uv_set_'.$yesterday->toDateString()) ?? 0;
        $uv->save();

        $pv = new VisitorStatistics;
        $pv->type = 'pv';
        $pv->date = $yesterday;
        $pv->count = Redis::get('pv_count_'.$yesterday->toDateString()) ?? 0;
        $pv->save();
    }
}
