<?php

namespace App\Jobs;

use App\Models\BlackList;
use App\Support\IpAddress;
use App\Support\SafeRequestUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BlackListLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // 最大失败次数
    public $tries = 5;

    // 超时
    public $timeout = 120;

    public $failOnTimeout = true;

    protected $blackListIp;

    protected $url;

    protected ?string $eventId = null;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($black_list_ip, $url)
    {
        $this->queue = 'black-list-log';
        $this->blackListIp = $black_list_ip;
        $this->url = SafeRequestUrl::sanitize((string) $url);
        $this->eventId = (string) Str::uuid();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Sanitize again because legacy queued payloads may predate URL filtering.
        $this->url = SafeRequestUrl::sanitize((string) $this->url);

        $targetIsId = is_int($this->blackListIp) ||
            (ctype_digit((string) $this->blackListIp) && filter_var((string) $this->blackListIp, FILTER_VALIDATE_IP) === false);
        $black_list = $targetIsId
            ? BlackList::withTrashed()->find($this->blackListIp)
            : IpAddress::findBlacklistRecord(BlackList::withTrashed(), (string) $this->blackListIp);

        if (! $black_list) {
            Log::error('black list log queue error.');
            Log::error('black list log target not found', [
                'target_type' => $targetIsId ? 'id' : 'ip',
            ]);

            return;
        }

        $blackListLog = $black_list->logs()->withTrashed()->firstOrCreate(
            ['event_id' => $this->eventId()],
            ['url' => $this->url]
        );

        if ((int) $blackListLog->black_list_id !== (int) $black_list->id) {
            throw new \RuntimeException('Blacklist log event is already associated with another blacklist.');
        }
    }

    private function eventId(): string
    {
        return $this->eventId ??= (string) ($this->job?->uuid() ?? Str::uuid());
    }
}
