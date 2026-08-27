<?php

namespace App\Console\Commands;

use App\Libraries\BingWallpaper\Contracts\BingWallpaperInterface;
use Illuminate\Console\Command;

class DownloadBingWallpaperCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'download:bing-wallpaper';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download bing wallpaper';

    protected $bingWallpaper;

    public function __construct(BingWallpaperInterface $bingWallpaper)
    {
        parent::__construct();

        $this->bingWallpaper = $bingWallpaper;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $today = today();
        $file_path = storage_path('bing-wallpaper/'.$today->year.'/'.$today->format('m'));
        $file_name = $today->toDateString().'.png';

        $res = $this->bingWallpaper->save($this->bingWallpaper->download(), $file_path, $file_name);

        $info = 'Download bing wallpaper '.$file_name.' '.($res ? 'success' : 'failed').'.';
        info($info);
        $this->info($info);
    }
}
