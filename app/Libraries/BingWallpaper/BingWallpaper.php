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
        $client = $this->makeClient();
        $response = $client->request('GET', 'https://bing.ioliu.cn/v1?w=1920&h=1200');

        $content = (string) $response->getBody()->getContents();
        if (@getimagesizefromstring($content) === false) {
            throw new \UnexpectedValueException('Wallpaper service did not return an image.');
        }

        return $content;
    }

    protected function makeClient(): Client
    {
        return new Client(['connect_timeout' => 5, 'timeout' => 15]);
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
