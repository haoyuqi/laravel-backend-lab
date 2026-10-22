<?php

namespace Tests\Feature\Jobs;

use App\Jobs\BlackListLog;
use App\Models\BlackList;
use App\Models\BlackListLog as BlackListLogModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
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

    public function test_mismatched_blacklist_log_relation_result_throws(): void
    {
        $blackList = BlackList::create(['ip' => '203.0.113.45']);
        $otherBlackList = BlackList::create(['ip' => '203.0.113.46']);
        $eventId = 'd5797fc3-4d13-4cdb-9826-9d5b273ce1f3';
        $blackList->logs()->create([
            'event_id' => $eventId,
            'url' => '/blocked',
        ]);

        Event::listen('eloquent.retrieved: '.BlackListLogModel::class, function (BlackListLogModel $log) use ($eventId, $otherBlackList): void {
            if ($log->event_id === $eventId) {
                $log->black_list_id = $otherBlackList->id;
            }
        });

        $job = new BlackListLog($blackList->id, '/blocked');
        (new \ReflectionProperty(BlackListLog::class, 'eventId'))->setValue($job, $eventId);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Blacklist log event is already associated with another blacklist.');
        $job->handle();
    }

    public function test_missing_target_is_logged_and_skipped(): void
    {
        Log::shouldReceive('error')->once()->with('black list log queue error.');
        Log::shouldReceive('error')->once()->with('black list log target not found', ['target_type' => 'ip']);

        (new BlackListLog('203.0.113.99', '/blocked'))->handle();

        $this->assertDatabaseCount('black_list_logs', 0);
    }
}
