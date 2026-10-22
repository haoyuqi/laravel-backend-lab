<?php

namespace Tests\Feature\Jobs;

use App\Jobs\RecordVisitors;
use App\Libraries\GetCityByIp\GetCityByIpAbstract;
use App\Models\Visitor;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class RecordVisitorsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Model::reguard();
    }

    public function test_existing_visitor_is_logged_without_geo_lookup(): void
    {
        $visitor = Visitor::create(['ip' => '203.0.113.11', 'city' => 'Existing']);
        $geo = $this->mock(GetCityByIpAbstract::class);
        $geo->shouldNotReceive('getCity');

        $job = new RecordVisitors($visitor->ip, '/account?token=secret');
        $urlProperty = new \ReflectionProperty(RecordVisitors::class, 'url');
        $this->assertSame('/account', $urlProperty->getValue($job));
        // Simulate an old serialized job that contains an unfiltered URL.
        $urlProperty->setValue($job, '/legacy?token=secret');
        $job->handle($geo);

        $this->assertDatabaseHas('visitor_logs', [
            'visitor_id' => $visitor->id,
            'url' => '/legacy',
        ]);
        $this->assertDatabaseMissing('visitor_logs', ['url' => '/legacy?token=secret']);
    }

    public function test_geoip_failure_does_not_prevent_logging(): void
    {
        $geo = $this->mock(GetCityByIpAbstract::class);
        $geo->shouldReceive('getCity')->once()->with('203.0.113.12')->andThrow(new \RuntimeException('GeoIP unavailable'));

        $job = new RecordVisitors('203.0.113.12', '/health');
        $job->handle($geo);

        $this->assertDatabaseHas('visitors', ['ip' => '203.0.113.12', 'city' => '']);
        $this->assertDatabaseHas('visitor_logs', ['url' => '/health']);
    }

    public function test_soft_deleted_visitor_can_receive_log_without_being_restored(): void
    {
        $visitor = Visitor::create(['ip' => '203.0.113.13', 'city' => 'Former']);
        $visitor->delete();
        $deletedAt = $visitor->deleted_at;
        $geo = $this->mock(GetCityByIpAbstract::class);
        $geo->shouldNotReceive('getCity');

        (new RecordVisitors($visitor->ip, '/archived-ip'))->handle($geo);

        $this->assertDatabaseHas('visitor_logs', ['visitor_id' => $visitor->id, 'url' => '/archived-ip']);
        $this->assertSame($deletedAt->toDateTimeString(), $visitor->fresh()->deleted_at->toDateTimeString());
    }

    public function test_retries_of_the_same_job_do_not_duplicate_the_log(): void
    {
        $visitor = Visitor::create(['ip' => '203.0.113.14', 'city' => 'Existing']);
        $geo = $this->mock(GetCityByIpAbstract::class);
        $geo->shouldNotReceive('getCity');
        $job = new RecordVisitors($visitor->ip, '/retry');

        $job->handle($geo);
        $job->handle($geo);

        $this->assertSame(1, $visitor->logs()->where('url', '/retry')->count());
    }

    public function test_legacy_job_without_event_id_uses_the_stable_queue_uuid(): void
    {
        $visitor = Visitor::create(['ip' => '203.0.113.15', 'city' => 'Existing']);
        $geo = $this->mock(GetCityByIpAbstract::class);
        $geo->shouldNotReceive('getCity');
        $job = new RecordVisitors($visitor->ip, '/legacy-retry');
        (new \ReflectionProperty(RecordVisitors::class, 'eventId'))->setValue($job, null);

        $queueJob = \Mockery::mock(Job::class);
        $queueJob->shouldReceive('uuid')->once()->andReturn('b0b32582-2ab9-4a37-9ef4-8d3d89496517');
        $job->setJob($queueJob);

        $job->handle($geo);
        $job->handle($geo);

        $this->assertSame(1, $visitor->logs()->where('url', '/legacy-retry')->count());
    }
}
