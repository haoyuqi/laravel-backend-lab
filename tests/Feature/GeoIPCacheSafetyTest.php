<?php

namespace Tests\Feature;

use App\Libraries\GetCityByIp\GetCityByIpAbstract;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GeoIPCacheSafetyTest extends TestCase
{
    public function test_geoip_resolution_and_clear_do_not_flush_file_cache(): void
    {
        $this->assertSafeCacheBehavior('file');
    }

    public function test_geoip_resolution_and_clear_do_not_flush_database_cache(): void
    {
        Schema::create('cache', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        try {
            $this->assertSafeCacheBehavior('database');
        } finally {
            Schema::dropIfExists('cache');
        }
    }

    private function assertSafeCacheBehavior(string $driver): void
    {
        $oldServerValue = $_SERVER['CACHE_STORE'] ?? null;
        $oldEnvValue = $_ENV['CACHE_STORE'] ?? null;
        $_SERVER['CACHE_STORE'] = $driver;
        $_ENV['CACHE_STORE'] = $driver;

        try {
            $geoipConfig = require config_path('geoip.php');
        } finally {
            if ($oldServerValue === null) {
                unset($_SERVER['CACHE_STORE']);
            } else {
                $_SERVER['CACHE_STORE'] = $oldServerValue;
            }
            if ($oldEnvValue === null) {
                unset($_ENV['CACHE_STORE']);
            } else {
                $_ENV['CACHE_STORE'] = $oldEnvValue;
            }
        }

        config([
            'cache.default' => $driver,
            'cache.stores.database.connection' => 'testing',
            'geoip' => $geoipConfig,
        ]);
        $this->app->forgetInstance('geoip');

        $sentinel = 'geoip-safety-'.uniqid('', true);
        Cache::store($driver)->put($sentinel, 'keep-me', 600);

        $package = app('geoip');
        $this->assertSame('127.0.0.0', $package->getLocation('127.0.0.1')->ip);
        $this->assertSame('', app(GetCityByIpAbstract::class)->getCity('127.0.0.1'));

        Artisan::call('geoip:clear');

        $this->assertSame('keep-me', Cache::store($driver)->get($sentinel));
        Cache::store($driver)->forget($sentinel);
    }
}
