<?php

namespace Tests\Feature\Jobs;

use App\Jobs\QueueJob;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class QueueJobTest extends TestCase
{
    public function test_it_writes_one_cache_value_for_each_requested_item(): void
    {
        Cache::flush();

        (new QueueJob(3))->handle();

        $this->assertSame('value:0', Cache::get('key:0'));
        $this->assertSame('value:1', Cache::get('key:1'));
        $this->assertSame('value:2', Cache::get('key:2'));
        $this->assertFalse(Cache::has('key:3'));
    }
}
