<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CorsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cors.paths' => ['api/*', 'test-cors'],
            'cors.allowed_methods' => ['*'],
            'cors.allowed_origins' => ['*'],
            'cors.allowed_headers' => ['*'],
            'cors.exposed_headers' => [],
            'cors.max_age' => 0,
            'cors.supports_credentials' => false,
        ]);

        Route::get('/test-cors', function () {
            return response()->json(['message' => 'cors-ok']);
        });

        Route::post('/test-cors', function () {
            return response()->json(['message' => 'cors-post-ok']);
        });
    }

    public function test_cors_preflight_request(): void
    {
        $response = $this->json('OPTIONS', '/test-cors', [], [
            'Origin' => 'https://example.com',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type, X-Requested-With',
        ]);

        $response->assertStatus(204)
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeader('Access-Control-Allow-Methods', 'POST');
    }

    public function test_cors_get_request_returns_allowed_origin(): void
    {
        $response = $this->get('/test-cors', [
            'Origin' => 'https://example.com',
        ]);

        $response->assertSuccessful()
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertJson(['message' => 'cors-ok']);
    }

    public function test_non_cors_path_does_not_return_cors_headers(): void
    {
        Route::get('/non-cors', function () {
            return response()->json(['message' => 'non-cors']);
        });

        $response = $this->get('/non-cors', [
            'Origin' => 'https://example.com',
        ]);

        $response->assertSuccessful()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
