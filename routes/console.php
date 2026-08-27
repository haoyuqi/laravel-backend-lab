<?php

use App\Events\PushTimeEvent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Push time event every minute
Schedule::call(function () {
    event(new PushTimeEvent);
})->everyMinute();

// Database and application backup
Schedule::command('backup:run')->dailyAt('01:00');
Schedule::command('backup:clean')->dailyAt('01:10');
Schedule::command('backup:monitor')->dailyAt('01:20');

// Visits and logs cleanup
Schedule::command('save:visits-count')->dailyAt('00:05');
Schedule::command('clear:files')->dailyAt('00:10');
Schedule::command('delete:redis-cache black_list_'.now()->subDay()->toDateString())->dailyAt('00:20');
Schedule::command('telescope:prune --hours=72')->dailyAt('00:30');
Schedule::command('download:bing-wallpaper')->dailyAt('05:00');
Schedule::command('delete:redis-cache black_list_'.now()->toDateString())->twiceDaily(7, 13);
Schedule::command('geoip:clear')->everySixHours();
