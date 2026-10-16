<?php

namespace App\Http\Middleware;

use App\Jobs\RecordVisitors as RecordVisitorsJob;
use App\Service\BlackListService;
use App\Support\IpAddress;
use App\Support\RedisFailureLogger;
use App\Support\SafeRequestUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordVisitors
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

        if (! app()->isLocal()) {
            $ip = $request->getClientIp();
            if ($ip === null) {
                return $next($request);
            }
            $ip = IpAddress::normalize($ip);
            $requestUrl = SafeRequestUrl::sanitize($request->getRequestUri());
            $blackListService = app()->make(BlackListService::class);

            if ($blackListService->checkIp($ip, $requestUrl)) {
                abort(403);
            }

            try {
                dispatch(new RecordVisitorsJob($ip, $requestUrl));
            } catch (\Throwable $e) {
                RedisFailureLogger::report('visitor recording dispatch failure', [
                    'ip' => $ip,
                    'url' => $requestUrl,
                    'exception_class' => $e::class,
                ]);
            }
        }

        return $next($request);
    }
}
