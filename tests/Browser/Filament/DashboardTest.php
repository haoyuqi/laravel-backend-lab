<?php

namespace Tests\Browser\Filament;

use App\Models\BlackList;
use App\Models\User;
use App\Models\Visitor;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class DashboardTest extends DuskTestCase
{
    public function test_guest_is_redirected_to_filament_login(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/filament')
                ->assertPathIs('/filament/login');
        });
    }

    public function test_authenticated_admin_can_view_dashboard_with_stats_overview(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin', 'password' => bcrypt('password')]
        );

        Visitor::firstOrCreate(
            ['ip' => '127.0.0.1'],
            ['city' => 'Localhost']
        );

        BlackList::firstOrCreate(['ip' => '127.0.0.99']);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/filament')
                ->assertPathIs('/filament')
                ->assertSee('今日 PV')
                ->assertSee('今日 UV')
                ->assertSee('访客总数')
                ->assertSee('黑名单 IP');
        });
    }
}
