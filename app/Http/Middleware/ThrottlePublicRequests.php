<?php

namespace App\Http\Middleware;

use App\Support\RedisFailureLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottlePublicRequests
{
    /**
     * Public page rate limit per client IP. Kept lenient enough for normal
     * browsing while bounding anonymous traffic against the visitor pipeline.
     */
    private const MAX_ATTEMPTS = 60;

    private const DECAY_SECONDS = 60;

    /**
     * Handle an incoming request.
     *
     * Redis (or another cache backend) is optional for this control: a broken
     * limiter store must fail open so public pages keep working.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = 'public-web:'.$request->ip();

        try {
            if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
                return response('Too Many Requests', 429, [
                    'Retry-After' => (string) RateLimiter::availableIn($key),
                ]);
            }

            RateLimiter::hit($key, self::DECAY_SECONDS);
        } catch (\Throwable $exception) {
            RedisFailureLogger::report('public rate limiter failure', [
                'ip' => $request->ip(),
                'error' => $exception->getMessage(),
            ]);
        }

        return $next($request);
    }
}
