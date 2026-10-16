<?php

namespace Tests\Feature;

use App\Http\Middleware\CountPvAndUv;
use App\Http\Middleware\RecordVisitors;
use App\Jobs\QueueJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->withoutMiddleware([RecordVisitors::class, CountPvAndUv::class]);
        Queue::fake();
    }

    public function test_create_requires_post_and_validates_workload(): void
    {
        $this->get('/queue/create?count=10')->assertStatus(405);
        foreach ([[], ['count' => 'invalid'], ['count' => 0], ['count' => 1001]] as $input) {
            $this->post('/queue/create', $input)
                ->assertRedirect('/error')->assertSessionHasErrors(['count']);
        }
        Queue::assertNothingPushed();

        $this->post('/queue/create', ['count' => 10])->assertOk()
            ->assertViewIs('queue.create')->assertViewHas('info', 'success');
        Queue::assertPushed(QueueJob::class, 1);
    }

    public function test_disabled_demo_cannot_dispatch_jobs(): void
    {
        config(['demo.enabled' => false]);
        $this->post('/queue/create', ['count' => 10])->assertNotFound();
        Queue::assertNothingPushed();
    }
}
