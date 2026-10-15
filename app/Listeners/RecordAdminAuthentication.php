<?php

namespace App\Listeners;

use App\Models\User;
use App\Service\AdminOperationRecorder;
use App\Support\AdminAuthenticationContext;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecordAdminAuthentication
{
    // Registered explicitly; disable Laravel's convention-based handle discovery.
    public function captureLogin(Login $event): void
    {
        $context = app(AdminAuthenticationContext::class);
        $panel = Filament::getPanel('admin');
        if ($context->kind === 'login' && $event->guard === $panel->getAuthGuard() &&
            $event->user instanceof User && $event->user->canAccessPanel($panel)) {
            $context->pending = $event->user;
        }
    }

    public function recordLogout(Logout $event): void
    {
        $context = app(AdminAuthenticationContext::class);
        $panel = Filament::getPanel('admin');
        if ($context->kind !== 'logout' || $context->pending || $event->guard !== $panel->getAuthGuard() ||
            ! $event->user instanceof User || ! $event->user->canAccessPanel($panel)) {
            return;
        }
        $context->pending = $event->user;
        try {
            app(AdminOperationRecorder::class)->authentication($event->user, 'logout');
        } catch (Throwable) {
            $context->failed = true;
            // Never report database bindings, credentials, or the whole exception.
            Log::warning('Admin logout audit could not be persisted.');
        }
    }
}
