<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ErrorTest extends DuskTestCase
{
    /**
     * A Dusk test example.
     *
     * @return void
     */
    public function test_page()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/error')
                ->assertSee('No Message');
        });
    }
}
