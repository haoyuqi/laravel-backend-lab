<?php

namespace App\Service;

use App\Jobs\BlackListLog;
use App\Models\BlackList;
use App\Support\IpAddress;
use App\Support\RedisFailureLogger;
use App\Support\SafeRequestUrl;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Redis;

class BlackListService
{
    private $blackModel;

    public function __construct(BlackList $blackList)
    {
        $this->blackModel = $blackList;
    }

    public function cacheKey(?CarbonInterface $date = null): string
    {
        return 'black_list_'.($date ?? now())->toDateString();
    }

    public function forgetIp(string $ip): void
    {
        try {
            Redis::hdel($this->cacheKey(), $ip);
        } catch (\Throwable $e) {
            RedisFailureLogger::report('blacklist cache invalidation failure', ['ip' => $ip, 'error' => $e->getMessage()]);
        }
    }

    public function checkIp($ip, $url)
    {
        $ip = IpAddress::normalize((string) $ip);
        $blacklistRecord = IpAddress::findBlacklistRecord($this->blackModel->newQuery(), $ip);

        if ($blacklistRecord) {
            $this->recordBlockedRequest((int) $blacklistRecord->getKey(), (string) $url);
        }

        return (bool) $blacklistRecord;
    }

    private function recordBlockedRequest(int $blackListId, string $url): void
    {
        $url = SafeRequestUrl::sanitize($url);

        try {
            dispatch(new BlackListLog($blackListId, $url));
        } catch (\Throwable $e) {
            RedisFailureLogger::report('blacklist logging dispatch failure', [
                'black_list_id' => $blackListId,
                'url' => $url,
                'exception_class' => $e::class,
            ]);
        }
    }
}
