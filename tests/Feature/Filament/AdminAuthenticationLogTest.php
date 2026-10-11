<?php

namespace Tests\Feature\Filament;

use App\Filament\Auth\Login;
use App\Models\AdminOperationLog;
use App\Models\User;
use App\Support\AdminAuthenticationContext;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Event;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class AdminAuthenticationLogTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['email' => 'audit-admin@example.com', 'password' => 'password']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_successful_backend_login_and_logout_each_log_once(): void
    {
        $this->get('/admin/login')->assertSuccessful();
        $this->login()->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($this->admin);
        $this->assertSame('login', AdminOperationLog::sole()->action);
        $this->assertNull(AdminOperationLog::sole()->subject_id);
        $this->post('/admin/logout')->assertRedirect();
        $this->assertGuest();
        $this->assertSame(['login', 'logout'], AdminOperationLog::orderBy('id')->pluck('action')->all());
        $this->assertNull(app(AdminAuthenticationContext::class)->kind);
    }

    public function test_wrong_password_and_denied_account_are_not_logged(): void
    {
        Livewire::test(Login::class)->fillForm(['email' => $this->admin->email, 'password' => 'wrong'])->call('authenticate')->assertHasFormErrors();
        config(['app.admin_emails' => ['different@example.com']]);
        $this->login()->assertHasFormErrors();
        $this->assertGuest();
        $this->assertDatabaseCount('admin_operation_logs', 0);
        $this->assertNull(app(AdminAuthenticationContext::class)->kind);
    }

    public function test_shared_web_guard_authentication_outside_backend_is_not_logged(): void
    {
        auth()->attempt(['email' => $this->admin->email, 'password' => 'password']);
        auth()->logout();
        $this->assertDatabaseCount('admin_operation_logs', 0);
    }

    public function test_failure_after_login_event_before_parent_returns_leaves_no_success_or_authentication(): void
    {
        app()->bind(LoginResponse::class, fn () => throw new RuntimeException('Response resolution failed'));
        $this->login()->assertHasFormErrors(['email']);
        $this->assertCompensated();
    }

    public function test_final_audit_failure_compensates_session_and_queued_remember_cookie(): void
    {
        Event::listen('eloquent.creating: '.AdminOperationLog::class, fn () => throw new RuntimeException('Audit unavailable'));
        $this->login()->assertHasFormErrors(['email']);
        $this->assertCompensated();
    }

    public function test_logout_remains_effective_when_audit_storage_fails(): void
    {
        $this->login()->assertHasNoFormErrors();
        Event::listen('eloquent.creating: '.AdminOperationLog::class, fn () => throw new RuntimeException('Audit unavailable'));
        $this->post('/admin/logout')->assertRedirect();
        $this->assertGuest();
        $this->assertDatabaseCount('admin_operation_logs', 1);
        $this->assertNull(app(AdminAuthenticationContext::class)->kind);
    }

    public function test_failed_login_remember_cookie_cannot_restore_authentication(): void
    {
        $remember = null;
        Event::listen('eloquent.creating: '.AdminOperationLog::class, function () use (&$remember): void {
            $remember = Cookie::queued(Filament::auth()->getRecallerName())?->getValue();
            throw new RuntimeException('Audit unavailable');
        });
        $this->login()->assertHasFormErrors(['email']);
        $this->assertCompensated();
        $this->assertNotNull($remember);
        $cookieName = Filament::auth()->getRecallerName();
        auth()->forgetGuards();
        $this->withCookie($cookieName, $remember)->get('/admin')->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_remember_token_revocation_failure_still_clears_local_authentication(): void
    {
        Event::listen('eloquent.creating: '.AdminOperationLog::class, function (): void {
            Event::listen('eloquent.updating: '.User::class, fn () => throw new RuntimeException('Token update unavailable'));
            throw new RuntimeException('Audit unavailable');
        });
        $this->login()->assertHasFormErrors(['email']);
        $this->assertCompensated();
    }

    public function test_guest_logout_and_reading_backend_do_not_produce_logs(): void
    {
        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->actingAs($this->admin)->get('/admin')->assertSuccessful();
        $this->get('/admin/admin-operation-logs')->assertSuccessful();
        $this->assertDatabaseCount('admin_operation_logs', 0);
    }

    private function login(): Testable
    {
        return Livewire::test(Login::class)->fillForm([
            'email' => $this->admin->email, 'password' => 'password', 'remember' => true,
        ])->call('authenticate');
    }

    private function assertCompensated(): void
    {
        $this->assertGuest();
        $guard = Filament::auth();
        $this->assertNull(session()->get($guard->getName()));
        $cookie = Cookie::queued($guard->getRecallerName());
        $this->assertTrue($cookie === null || $cookie->getExpiresTime() < time());
        $this->assertDatabaseCount('admin_operation_logs', 0);
        $this->assertNull(app(AdminAuthenticationContext::class)->kind);
        // A fresh request must not regain authentication from the previous session.
        $this->get('/admin')->assertRedirect('/admin/login');
    }
}
