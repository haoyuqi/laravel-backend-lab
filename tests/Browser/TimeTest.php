<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TimeTest extends DuskTestCase
{
    /**
     * A Dusk test example.
     *
     * @return void
     */
    public function test_page()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/time')
                ->assertSee(now()->format('Y-m-d H:i'));
        });
    }
}
