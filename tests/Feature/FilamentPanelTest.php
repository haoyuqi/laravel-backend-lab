<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Pages\Auth\Login;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentPanelTest extends TestCase
{
    public function test_filament_login_page_loads_successfully(): void
    {
        $response = $this->get('/admin/login');

        $response->assertSuccessful();
    }

    public function test_legacy_filament_path_is_not_available(): void
    {
        $this->get('/filament')->assertNotFound();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/admin/login');
    }

    public function test_authenticated_user_can_access_filament_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertSuccessful();
    }

    public function test_admin_can_log_in_via_filament_login_page(): void
    {
        $user = User::factory()->create([
            'email' => 'filament-admin@example.com',
            'password' => bcrypt('password'),
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'filament-admin@example.com',
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_cannot_log_in_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'filament-admin@example.com',
            'password' => bcrypt('password'),
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'filament-admin@example.com',
                'password' => 'wrong-password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_authenticated_admin_can_log_out(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/admin/logout');

        $response->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_user_cannot_access_panel_in_production_if_not_in_admin_emails(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.admin_emails' => ['allowed@example.com']]);

        $allowedUser = User::factory()->create(['email' => 'allowed@example.com']);
        $deniedUser = User::factory()->create(['email' => 'denied@example.com']);

        $this->actingAs($deniedUser)->get('/admin')->assertForbidden();
        $this->actingAs($allowedUser)->get('/admin')->assertSuccessful();
    }

    public function test_user_cannot_access_panel_in_production_if_admin_emails_is_empty(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.admin_emails' => []]);

        $user = User::factory()->create(['email' => 'any@example.com']);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_user_can_access_panel_in_non_production_when_admin_emails_is_empty(): void
    {
        config(['app.admin_emails' => []]);

        $user = User::factory()->create(['email' => 'random@example.com']);

        $this->actingAs($user)->get('/admin')->assertSuccessful();
    }

    public function test_user_can_access_panel_with_case_insensitive_whitelisted_email_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.admin_emails' => ['allowed@example.com']]);

        $mixedCaseUser = User::factory()->create(['email' => 'Allowed@EXAMPLE.COM']);

        $this->actingAs($mixedCaseUser)->get('/admin')->assertSuccessful();
    }
}
