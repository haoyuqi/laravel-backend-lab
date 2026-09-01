<?php

namespace Tests\Browser\Filament;

use App\Models\BlackList;
use App\Models\BlackListLog;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class BlackListTest extends DuskTestCase
{
    public function test_guest_is_redirected_to_filament_login(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/filament/black-lists')
                ->assertPathIs('/filament/login');
        });
    }

    public function test_authenticated_admin_can_view_black_lists_and_create(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin', 'password' => bcrypt('password')]
        );

        $blackList = BlackList::firstOrCreate(['ip' => '127.0.0.99']);

        $this->browse(function (Browser $browser) use ($admin, $blackList) {
            $browser->loginAs($admin)
                ->visit('/filament/black-lists')
                ->assertPathIs('/filament/black-lists')
                ->assertSee('黑名单列表')
                ->assertSee($blackList->ip)
                ->assertSee('今日拦截量')
                ->assertSee('历史拦截量')
                ->assertSee('添加时间')
                ->assertSee('最后拦截时间');
        });
    }

    public function test_authenticated_admin_can_view_black_list_logs(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin', 'password' => bcrypt('password')]
        );

        $blackList = BlackList::firstOrCreate(['ip' => '127.0.0.99']);
        $log = new BlackListLog;
        $log->url = 'https://example.com/blocked-dusk';
        $blackList->logs()->save($log);

        $this->browse(function (Browser $browser) use ($admin, $blackList, $log) {
            $browser->loginAs($admin)
                ->visit('/filament/black-list-logs')
                ->assertPathIs('/filament/black-list-logs')
                ->assertSee('拦截日志')
                ->assertSee($blackList->ip)
                ->assertSee($log->url)
                ->assertSee('IP')
                ->assertSee('URL')
                ->assertSee('拦截时间');
        });
    }
}
