<?php

namespace App\Jobs;

use App\Libraries\GetCityByIp\GetCityByIpAbstract;
use App\Models\Visitor;
use App\Support\SafeRequestUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RecordVisitors implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // 最大失败次数
    public $tries = 5;

    // 超时
    public $timeout = 120;

    public $failOnTimeout = true;

    protected $ip;

    protected $url;

    protected ?string $eventId = null;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($ip, $url)
    {
        $this->queue = 'record-visitor';
        $this->ip = $ip;
        $this->url = SafeRequestUrl::sanitize((string) $url);
        $this->eventId = (string) Str::uuid();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(GetCityByIpAbstract $getCityByIp)
    {
        // Sanitize again because already-queued payloads may contain full URLs.
        $this->url = SafeRequestUrl::sanitize((string) $this->url);

        // Preserve logs for soft-deleted visitors (the IP remains unique), and
        // avoid doing a GeoIP lookup unless a visitor row must be created.
        $visitor = Visitor::withTrashed()->where('ip', $this->ip)->first();

        if (! $visitor) {
            $city = '';

            try {
                $city = (string) $getCityByIp->getCity($this->ip);
            } catch (\Throwable $exception) {
                Log::warning('visitor geoip lookup failed', [
                    'exception_class' => $exception::class,
                ]);
            }

            // createOrFirst handles a concurrent insert safely, including on
            // databases where a failed insert would otherwise poison a transaction.
            $visitor = Visitor::withTrashed()->firstOrCreate(
                ['ip' => $this->ip],
                ['city' => $city]
            );
        }

        $visitorLog = $visitor->logs()->withTrashed()->firstOrCreate(
            ['event_id' => $this->eventId()],
            ['url' => $this->url]
        );

        if ((int) $visitorLog->visitor_id !== (int) $visitor->id) {
            throw new \RuntimeException('Visitor log event is already associated with another visitor.');
        }
    }

    private function eventId(): string
    {
        // Legacy queued payloads predate event IDs. Laravel's queue UUID stays
        // stable across retries, so it can provide the same dedupe key.
        return $this->eventId ??= (string) ($this->job?->uuid() ?? Str::uuid());
    }
}
