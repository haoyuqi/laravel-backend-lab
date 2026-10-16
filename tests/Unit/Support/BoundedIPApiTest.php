<?php

namespace Tests\Unit\Support;

use App\Libraries\GetCityByIp\BoundedIPApi;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class BoundedIPApiTest extends TestCase
{
    public function test_it_caps_provider_timeouts_and_hydrates_a_successful_result(): void
    {
        $client = \Mockery::mock(Client::class);
        $client->shouldReceive('get')
            ->once()
            ->with('json/198.51.100.1', ['query' => ['fields' => 49663, 'lang' => 'zh-CN']])
            ->andReturn(new Response(200, [], json_encode([
                'status' => 'success',
                'countryCode' => 'CN',
                'country' => 'China',
                'city' => 'Hangzhou',
            ], JSON_THROW_ON_ERROR)));

        $service = new class(['timeout' => 60, 'lang' => 'zh-CN', 'continent_path' => null], $client) extends BoundedIPApi
        {
            private Client $mockClient;

            public float $timeoutSeen;

            public array $targetSeen;

            public function __construct(array $config, Client $mockClient)
            {
                $this->mockClient = $mockClient;
                parent::__construct($config);
            }

            protected function makeClient(float $timeout, string $scheme, string $host): Client
            {
                $this->timeoutSeen = $timeout;
                $this->targetSeen = [$scheme, $host];

                return $this->mockClient;
            }
        };

        $location = $service->locate('198.51.100.1');

        $this->assertSame(5.0, $service->timeoutSeen);
        $this->assertSame(['http', 'ip-api.com'], $service->targetSeen);
        $this->assertSame('Hangzhou', $location->city);
        $this->assertSame('CN', $location->iso_code);
    }
}
