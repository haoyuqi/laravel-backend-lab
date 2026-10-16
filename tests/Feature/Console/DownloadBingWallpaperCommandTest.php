<?php

namespace Tests\Feature\Console;

use App\Libraries\BingWallpaper\Contracts\BingWallpaperInterface;
use Illuminate\Console\Command;
use Tests\TestCase;

class DownloadBingWallpaperCommandTest extends TestCase
{
    public function test_download_failure_returns_nonzero_without_saving(): void
    {
        $this->mock(BingWallpaperInterface::class, function ($mock) {
            $mock->shouldReceive('download')->once()->andThrow(new \RuntimeException('Unavailable'));
            $mock->shouldNotReceive('save');
        });
        $this->artisan('download:bing-wallpaper')->assertExitCode(Command::FAILURE);
    }

    public function test_failed_file_write_returns_nonzero(): void
    {
        $this->mock(BingWallpaperInterface::class, function ($mock) {
            $mock->shouldReceive('download')->once()->andReturn('test-image');
            $mock->shouldReceive('save')->once()->andReturn(false);
        });
        $this->artisan('download:bing-wallpaper')->assertExitCode(Command::FAILURE);
    }
}
