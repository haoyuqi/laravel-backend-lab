<?php

namespace Tests\Feature;

use App\Http\Middleware\CountPvAndUv;
use App\Http\Middleware\RecordVisitors;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SortControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->withoutMiddleware([RecordVisitors::class, CountPvAndUv::class]);
    }

    public static function algorithms(): array
    {
        return [['bubble'], ['quick'], ['select'], ['insert']];
    }

    #[DataProvider('algorithms')]
    public function test_sort_validates_input_and_returns_sorted_values(string $algorithm): void
    {
        foreach (['', '?count=invalid', '?count=0', '?count=1001'] as $query) {
            $this->get('/sort/'.$algorithm.$query)
                ->assertRedirect('/error')->assertSessionHasErrors(['count']);
        }

        $this->get('/sort/'.$algorithm.'?count=50')
            ->assertOk()->assertExactJson(range(1, 50));
    }

    public function test_demo_is_disabled_by_default_configuration(): void
    {
        config(['demo.enabled' => false]);
        $this->get('/sort/bubble?count=10')->assertNotFound();
    }

    public function test_demo_requests_share_a_per_ip_rate_limit(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.125']);
        for ($i = 0; $i < 10; $i++) {
            $this->get('/sort/quick?count=1')->assertOk();
        }
        $this->get('/sort/bubble?count=1')->assertStatus(429);
    }
}
