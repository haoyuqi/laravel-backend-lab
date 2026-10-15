<?php

namespace Tests\Unit\Support;

use App\Support\VisitorStatisticsRetention;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class VisitorStatisticsRetentionTest extends TestCase
{
    public function test_deadline_uses_calendar_days_in_the_application_timezone_without_mutating_the_date(): void
    {
        $date = Carbon::parse('2026-10-24 23:59:59', 'Europe/Berlin');
        $original = $date->copy();
        $deadline = Carbon::parse('2026-11-01 00:00:00', 'Europe/Berlin');
        $this->assertSame($deadline->getTimestamp(), VisitorStatisticsRetention::expiresAt($date));
        $this->assertTrue($date->equalTo($original));
        $this->assertSame(VisitorStatisticsRetention::expiresAt($date), VisitorStatisticsRetention::expiresAt($date->copy()->startOfDay()));
    }
}
