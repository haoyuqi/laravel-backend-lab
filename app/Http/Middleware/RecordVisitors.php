<?php

namespace App\Http\Middleware;

use App\Jobs\RecordVisitors as RecordVisitorsJob;
use App\Service\BlackListService;
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
        if (! app()->isLocal()) {
            $ip = $request->getClientIp();
            $request_url = $request->getRequestUri();
            $black_list_service = app()->make(BlackListService::class);

            if ($black_list_service->checkIp($ip, $request_url)) {
                abort(403);
            }

            dispatch(new RecordVisitorsJob($ip, $request_url));
        }

        return $next($request);
    }
}
