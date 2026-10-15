<?php

namespace App\Support;

use Carbon\CarbonInterface;

class VisitorStatisticsRetention
{
    // Seven complete days after the statistics date, including scheduler retries.
    public static function expiresAt(CarbonInterface $date): int
    {
        return $date->copy()->startOfDay()->addDays(8)->getTimestamp();
    }
}
