<?php

namespace App\Http\Middleware;

use App\Support\AdminAuthenticationContext;
use Closure;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordAdminLogout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('filament.admin.auth.logout')) {
            return $next($request);
        }

        $context = app(AdminAuthenticationContext::class);

        return $context->within('logout', function () use ($request, $next, $context): Response {
            try {
                $response = $next($request);
            } finally {
                Filament::auth()->logoutCurrentDevice();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            if ($context->failed) {
                Notification::make()->title('已退出登录，但退出日志暂时无法保存。')->warning()->send();
            }

            return $response;
        });
    }
}
