<?php

namespace Tests\Feature\Libraries;

use App\Libraries\BingWallpaper\BingWallpaper;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BingWallpaperTest extends TestCase
{
    public function test_default_client_factory_preserves_the_connection_and_request_timeouts(): void
    {
        $factory = new \ReflectionMethod(BingWallpaper::class, 'makeClient');
        $client = $factory->invoke(new BingWallpaper);

        $this->assertSame(5, $client->getConfig('connect_timeout'));
        $this->assertSame(15, $client->getConfig('timeout'));
    }

    public function test_download_returns_content_when_the_service_responds_with_an_image(): void
    {
        $content = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j4n8AAAAASUVORK5CYII=');
        $client = \Mockery::mock(Client::class);
        $client->shouldReceive('request')
            ->once()
            ->with('GET', 'https://bing.ioliu.cn/v1?w=1920&h=1200')
            ->andReturn(new Response(200, [], $content));

        $wallpaper = new class($client) extends BingWallpaper
        {
            public function __construct(private Client $client) {}

            protected function makeClient(): Client
            {
                return $this->client;
            }
        };

        $this->assertSame($content, $wallpaper->download());
    }

    public function test_download_rejects_non_image_responses(): void
    {
        $client = \Mockery::mock(Client::class);
        $client->shouldReceive('request')->once()->andReturn(new Response(200, [], 'not an image'));
        $wallpaper = new class($client) extends BingWallpaper
        {
            public function __construct(private Client $client) {}

            protected function makeClient(): Client
            {
                return $this->client;
            }
        };

        $this->expectException(\UnexpectedValueException::class);
        $wallpaper->download();
    }

    public function test_save_creates_a_nested_directory_and_writes_the_named_image(): void
    {
        $directory = storage_path('framework/testing/bing-wallpaper/nested');
        File::deleteDirectory(storage_path('framework/testing/bing-wallpaper'));

        try {
            $this->assertTrue((new BingWallpaper)->save('image-bytes', $directory, 'fixture.png'));
            $this->assertSame('image-bytes', File::get($directory.'/fixture.png'));
        } finally {
            File::deleteDirectory(storage_path('framework/testing/bing-wallpaper'));
        }
    }
}
