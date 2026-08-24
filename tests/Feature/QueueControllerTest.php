<?php

namespace Tests\Feature;

use Tests\TestCase;

class QueueControllerTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_create()
    {
        $response = $this->get('/queue/create');
        $response->assertStatus(302)
            ->assertRedirect(url()->action('IndexController@error'))
            ->assertSessionHasErrors(['count']);

        $string = chr(mt_rand(97, 122));
        $response = $this->get('/queue/create?count='.$string);
        $response->assertStatus(302)
            ->assertRedirect(url()->action('IndexController@error'))
            ->assertSessionHasErrors(['count']);

        $count = 0;
        $response = $this->get('/queue/create?count='.$count);
        $response->assertStatus(302)
            ->assertRedirect(url()->action('IndexController@error'))
            ->assertSessionHasErrors(['count']);

        $count = mt_rand(10001, mt_getrandmax());
        $response = $this->get('/queue/create?count='.$count);
        $response->assertStatus(302)
            ->assertRedirect(url()->action('IndexController@error'))
            ->assertSessionHasErrors(['count']);

        $count = mt_rand(1, 10000);
        $response = $this->get('/queue/create?count='.$count);
        $response->assertSuccessful()
            ->assertViewIs('queue.create')
            ->assertSeeText('laravel example')
            ->assertViewHas('info', 'success');
    }
}
