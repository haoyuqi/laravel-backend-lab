<?php

namespace App\Providers;

use App\Libraries\BingWallpaper\BingWallpaper;
use App\Libraries\BingWallpaper\Contracts\BingWallpaperInterface;
use App\Libraries\GetCityByIp\GeoIP;
use App\Libraries\GetCityByIp\GetCityByIpAbstract;
use App\Listeners\RecordAdminAuthentication;
use App\Models\BlackList;
use App\Observers\AdminOperationObserver;
use App\Observers\BlackListObserver;
use App\Support\AdminAuthenticationContext;
use App\Support\AdminOperationContext;
use App\Support\RedisFailureLogger;
use GuzzleHttp\Client;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(TelescopeServiceProvider::class);
        $this->app->singleton(RedisFailureLogger::class);
        $this->app->scoped(AdminOperationContext::class);
        $this->app->scoped(AdminAuthenticationContext::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        RateLimiter::for('demo', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        $this->app->bind(GetCityByIpAbstract::class, function ($app) {
            return new GeoIP(new Client);
        });

        $this->app->bind(
            BingWallpaperInterface::class,
            BingWallpaper::class
        );

        BlackList::observe(BlackListObserver::class);
        Event::listen('eloquent.*', function (string $event, array $payload): void {
            app(AdminOperationObserver::class)->handle($event, $payload);
        });
        Event::listen(Login::class, [RecordAdminAuthentication::class, 'captureLogin']);
        Event::listen(Logout::class, [RecordAdminAuthentication::class, 'recordLogout']);
    }
}
