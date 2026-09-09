<?php

namespace App\Libraries\BingWallpaper\Contracts;

interface BingWallpaperInterface
{
    public function download();

    public function save($content, $save_path, $file_name = null);
}
