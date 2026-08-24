<?php

namespace App\Console;

use App\Console\Commands\ClearFilesCommand;
use App\Console\Commands\DeleteRedisCacheCommand;
use App\Console\Commands\DownloadBingWallpaperCommand;
use App\Console\Commands\SaveVisitsCountCommand;
use App\Events\PushTimeEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        ClearFilesCommand::class,
        SaveVisitsCountCommand::class,
        DeleteRedisCacheCommand::class,
        DownloadBingWallpaperCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')
        //          ->hourly();

        $schedule->call(function () {
            event(new PushTimeEvent);
        })->everyMinute();

        $schedule->command('backup:run')->dailyAt('01:00');
        $schedule->command('backup:clean')->dailyAt('01:10');
        $schedule->command('backup:monitor')->dailyAt('01:20');

        $schedule->command('save:visits-count')->dailyAt('00:05');
        $schedule->command('clear:files')->dailyAt('00:10');
        $schedule->command('delete:redis-cache black_list_'.now()->subDay()->toDateString())->dailyAt('00:20');
        $schedule->command('telescope:prune --hours=72')->dailyAt('00:30');
        $schedule->command('download:bing-wallpaper')->dailyAt('05:00');
        $schedule->command('delete:redis-cache black_list_'.now()->toDateString())->twiceDaily(7, 13);
        $schedule->command('geoip:clear')->everySixHours();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
