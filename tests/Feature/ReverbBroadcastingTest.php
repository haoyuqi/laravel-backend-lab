<?php

namespace Tests\Feature;

use App\Events\PushTimeEvent;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Broadcasting\Factory;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReverbBroadcastingTest extends TestCase
{
    public function test_time_event_keeps_its_public_channel_payload_and_queue(): void
    {
        $this->travelTo(now()->startOfMinute());
        Queue::fake();

        event(new PushTimeEvent);

        Queue::assertPushedOn('push-time', BroadcastEvent::class, function (BroadcastEvent $job): bool {
            $this->assertSame('push-time', $job->event->broadcastOn()->name);
            $this->assertSame(['time' => now()->toDateTimeString()], $job->event->broadcastWith());

            return true;
        });
    }

    public function test_reverb_sends_the_existing_event_without_a_redis_prefix(): void
    {
        $this->assertSame('reverb', config('broadcasting.connections.reverb.driver'));
        config()->set('broadcasting.default', 'reverb');
        config()->set('broadcasting.connections.reverb', [
            'driver' => 'reverb',
            'key' => 'test-key',
            'secret' => 'unused-test-value',
            'app_id' => 'test-app',
            'options' => ['host' => 'localhost', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false],
        ]);

        config()->set('broadcasting.connections.reverb.client_options.handler', function ($request) {
            $this->assertSame('localhost:8080', $request->getUri()->getAuthority());
            $payload = json_decode((string) $request->getBody(), true);
            $this->assertSame('push-time', $payload['channel']);
            $this->assertSame(PushTimeEvent::class, $payload['name']);
            $this->assertArrayHasKey('time', json_decode($payload['data'], true));

            return new FulfilledPromise(new Response(200, [], '{}'));
        });

        (new BroadcastEvent(new PushTimeEvent))->handle(app(Factory::class));
    }
}
