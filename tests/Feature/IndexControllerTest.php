<?php

namespace Tests\Feature;

use Tests\TestCase;

class IndexControllerTest extends TestCase
{
    public function test_index()
    {
        $response = $this->get('/');

        $response->assertSuccessful()
            ->assertViewIs('index.welcome')
            ->assertSeeText('Laravel example')
            ->assertViewHas('info', 'Hello World');
    }

    public function test_error()
    {
        $response = $this->get('/error');

        $response->assertSuccessful()
            ->assertViewIs('index.error')
            ->assertSeeText('Error')
            ->assertViewHas('info', 'No Message');
    }

    public function time()
    {
        $response = $this->get('/time');

        $response->assertSuccessful()
            ->assertViewIs('index.time')
            ->assertSeeText('Time')
            ->assertViewHas('info', now()->toDateTimeString());
    }

    public function test_test()
    {
        $response = $this->get('/test');

        $response->assertForbidden();
    }
}
