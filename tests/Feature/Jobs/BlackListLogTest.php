<?php

namespace Tests\Feature\Jobs;

use App\Jobs\BlackListLog;
use App\Models\BlackList;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class BlackListLogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Model::reguard();
    }

    public function test_it_accepts_a_persisted_record_id_and_sanitizes_urls(): void
    {
        $blackList = BlackList::create(['ip' => '203.0.113.41']);
        $job = new BlackListLog($blackList->id, '/blocked?token=secret');
        $urlProperty = new \ReflectionProperty(BlackListLog::class, 'url');
        $this->assertSame('/blocked', $urlProperty->getValue($job));
        // A legacy queued payload may have serialized the full URL.
        $urlProperty->setValue($job, '/legacy-blocked?token=secret');

        $job->handle();

        $this->assertDatabaseHas('black_list_logs', [
            'black_list_id' => $blackList->id,
            'url' => '/legacy-blocked',
        ]);
        $this->assertDatabaseMissing('black_list_logs', ['url' => '/legacy-blocked?token=secret']);
    }

    public function test_retry_of_the_same_job_does_not_duplicate_a_log(): void
    {
        $blackList = BlackList::create(['ip' => '203.0.113.42']);
        $job = new BlackListLog($blackList->ip, '/blocked');

        $job->handle();
        $job->handle();

        $this->assertSame(1, $blackList->logs()->where('url', '/blocked')->count());
    }

    public function test_id_target_survives_an_ip_edit_and_soft_delete_before_execution(): void
    {
        $blackList = BlackList::create(['ip' => '203.0.113.43']);
        $job = new BlackListLog($blackList->id, '/blocked');
        $blackList->update(['ip' => '203.0.113.44']);
        $blackList->delete();

        $job->handle();

        $this->assertDatabaseHas('black_list_logs', [
            'black_list_id' => $blackList->id,
            'url' => '/blocked',
        ]);
    }
}
