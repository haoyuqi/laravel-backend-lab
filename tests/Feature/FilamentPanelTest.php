<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Pages\Auth\Login;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentPanelTest extends TestCase
{
    use DatabaseTransactions;

    public function test_filament_login_page_loads_successfully(): void
    {
        $response = $this->get('/filament/login');

        $response->assertSuccessful();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/filament');

        $response->assertRedirect('/filament/login');
    }

    public function test_authenticated_user_can_access_filament_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/filament');

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
            ->assertRedirect('/filament');

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

        $response = $this->actingAs($user)->post('/filament/logout');

        $response->assertRedirect('/filament/login');
        $this->assertGuest();
    }
}
