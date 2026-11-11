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

    public function test_time(): void
    {
        $this->travelTo(now()->startOfSecond());

        $response = $this->get('/time');

        $response->assertSuccessful()
            ->assertViewIs('index.time')
            ->assertSeeText('Time')
            ->assertViewHas('init_data', [now()->toDateTimeString()])
            ->assertDontSee('window.__REVERB_CLIENT__', false);
    }

    public function test_time_renders_the_runtime_reverb_client_configuration(): void
    {
        config()->set('reverb.client', [
            'key' => 'test-key',
            'host' => 'ws.example.test',
            'port' => 8081,
            'scheme' => 'https',
        ]);

        $this->get('/time')
            ->assertSuccessful()
            ->assertSee('window.__REVERB_CLIENT__', false)
            ->assertSee('"key":"test-key"', false)
            ->assertSee('"host":"ws.example.test"', false)
            ->assertSee('"port":8081', false)
            ->assertSee('"scheme":"https"', false);
    }

    public function test_time_escapes_the_runtime_reverb_client_configuration(): void
    {
        config()->set('reverb.client', [
            'key' => '</script><script>alert(1)</script>',
            'host' => 'ws.example.test',
            'port' => 8081,
            'scheme' => 'https',
        ]);

        $this->get('/time')
            ->assertSuccessful()
            ->assertDontSee('</script><script>alert(1)</script>', false)
            ->assertSee('\u003C\/script\u003E', false);
    }

    public function test_test()
    {
        $response = $this->get('/test');

        $response->assertForbidden();
    }
}
