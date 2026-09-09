<?php

namespace App\Libraries\BingWallpaper;

use App\Libraries\BingWallpaper\Contracts\BingWallpaperInterface;
use GuzzleHttp\Client;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;

class BingWallpaper implements BingWallpaperInterface
{
    public function download(): string
    {
        $client = new Client;
        $response = $client->request('GET', 'https://bing.ioliu.cn/v1?w=1920&h=1200');

        return (string) $response->getBody()->getContents();
    }

    public function save($content, $save_path, $file_name = null): bool
    {
        $filesystem = new Filesystem;

        $file_name = $file_name ?? Carbon::today()->toDateString().'.png';

        if (! $filesystem->exists($save_path)) {
            $filesystem->makeDirectory($save_path, 0755, true);
        }

        return (bool) $filesystem->put($save_path.'/'.$file_name, $content);
    }
}
