<?php

namespace App\Http\Middleware;

use App\Support\RedisFailureLogger;
use App\Support\VisitorStatisticsRetention;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class CountPvAndUv
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $healthPath = ltrim((string) config('app.health_path', '/up'), '/');
        if (($healthPath !== '' && $request->is($healthPath)) || $request->is('health/*')) {
            return $next($request);
        }

        $ip = $request->getClientIp();
        $date = now();
        $uvKey = 'uv_set_'.$date->toDateString();
        $pvKey = 'pv_count_'.$date->toDateString();

        try {
            Redis::sadd($uvKey, $ip);
            Redis::incr($pvKey);
        } catch (\Throwable $e) {
            RedisFailureLogger::report('pv/uv count failure', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);
        }

        // Maintain retention even if only one counter was successfully written.
        // TTL failures must not skip valid counts or the other key's expiration.
        foreach ([$uvKey, $pvKey] as $key) {
            try {
                Redis::expireat($key, VisitorStatisticsRetention::expiresAt($date));
            } catch (\Throwable $e) {
                RedisFailureLogger::report('pv/uv expiration failure', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $next($request);
    }
}
