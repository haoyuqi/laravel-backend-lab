<?php

namespace App\Filament\Auth;

use App\Service\AdminOperationRecorder;
use App\Support\AdminAuthenticationContext;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class Login extends \Filament\Auth\Pages\Login
{
    public function authenticate(): ?LoginResponse
    {
        $context = app(AdminAuthenticationContext::class);

        return $context->within('login', function () use ($context): ?LoginResponse {
            try {
                $response = parent::authenticate();
                if ($response !== null && $context->pending) {
                    app(AdminOperationRecorder::class)->authentication($context->pending, 'login');
                }

                return $response;
            } catch (Throwable $exception) {
                if (! $context->pending) {
                    throw $exception;
                }
                $this->clearNewAuthentication($context);
                Log::warning('Admin login could not complete its audit.', ['exception_class' => get_class($exception), 'code' => $exception->getCode()]);
                throw ValidationException::withMessages(['data.email' => '无法完成后台登录，请稍后重试。']);
            }
        });
    }

    private function clearNewAuthentication(AdminAuthenticationContext $context): void
    {
        $guard = Filament::auth();
        try {
            // Login dispatch can occur before SessionGuard has set its user.
            $guard->setUser($context->pending);
            $guard->logout();
        } catch (Throwable) {
            Log::warning('Admin login remember-token revocation failed.');
        } finally {
            // Current-device logout does not require a database token update.
            $guard->logoutCurrentDevice();
            Cookie::unqueue($guard->getRecallerName());
            Cookie::queue(Cookie::forget($guard->getRecallerName()));
            session()->invalidate();
            session()->regenerateToken();
        }
    }
}
