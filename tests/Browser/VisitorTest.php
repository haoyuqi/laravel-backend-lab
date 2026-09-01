<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Visitor;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class VisitorTest extends DuskTestCase
{
    public function test_guest_is_redirected_to_filament_login(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/filament/visitors')
                ->assertPathIs('/filament/login');
        });
    }

    public function test_authenticated_admin_can_view_visitors_list(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin', 'password' => bcrypt('password')]
        );

        $visitor = Visitor::firstOrCreate(
            ['ip' => '127.0.0.1'],
            ['city' => 'Localhost']
        );

        $this->browse(function (Browser $browser) use ($admin, $visitor) {
            $browser->loginAs($admin)
                ->visit('/filament/visitors')
                ->assertPathIs('/filament/visitors')
                ->assertSee('访客列表')
                ->assertSee($visitor->ip)
                ->assertSee('今日访问量')
                ->assertSee('历史访问量')
                ->assertSee('黑名单')
                ->assertSee('首次访问')
                ->assertSee('最后访问')
                ->assertSee('查看');
        });
    }
}
