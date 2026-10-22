<?php

namespace Tests\Unit\Support;

use App\Libraries\GetCityByIp\BoundedIPApi;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use RuntimeException;
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

    public function test_it_uses_the_secure_keyed_provider_when_configured(): void
    {
        $client = \Mockery::mock(Client::class);
        $client->shouldReceive('get')
            ->once()
            ->with('json/198.51.100.2', ['query' => ['fields' => 49663, 'lang' => 'en', 'key' => 'provider-key']])
            ->andReturn(new Response(200, [], '{"status":"success","countryCode":"US"}'));
        $service = $this->service(['key' => 'provider-key', 'secure' => true], $client);

        $location = $service->locate('198.51.100.2');

        $this->assertSame(['https', 'pro.ip-api.com'], $service->targetSeen);
        $this->assertSame('Unknown', $location->continent);
    }

    public function test_it_translates_provider_transport_errors_without_leaking_details(): void
    {
        $client = \Mockery::mock(Client::class);
        $client->shouldReceive('get')->once()->andThrow(new RuntimeException('request contained a private key'));
        $service = $this->service([], $client);

        try {
            $service->locate('198.51.100.3');
            $this->fail('Provider transport errors must be translated.');
        } catch (RuntimeException $exception) {
            $this->assertSame('GeoIP provider request failed.', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }

    public function test_it_rejects_invalid_json_and_unsuccessful_provider_responses(): void
    {
        foreach ([
            new Response(200, [], '{invalid'),
            new Response(429, [], '{"status":"success"}'),
            new Response(200, [], '{"status":"fail"}'),
        ] as $response) {
            $client = \Mockery::mock(Client::class);
            $client->shouldReceive('get')->once()->andReturn($response);
            $service = $this->service([], $client);

            try {
                $service->locate('198.51.100.4');
                $this->fail('Invalid provider responses must be rejected.');
            } catch (RuntimeException $exception) {
                $this->assertStringStartsWith('GeoIP provider', $exception->getMessage());
                $this->assertSame(
                    str_contains((string) $response->getBody(), 'invalid') ? \JsonException::class : null,
                    $exception->getPrevious() ? $exception->getPrevious()::class : null
                );
            }
        }
    }

    public function test_it_loads_valid_continent_data_from_the_configured_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'continents-');
        file_put_contents($path, '{"CA":"North America"}');
        $client = \Mockery::mock(Client::class);
        $client->shouldReceive('get')
            ->once()
            ->andReturn(new Response(200, [], '{"status":"success","countryCode":"CA"}'));

        try {
            $location = $this->service(['continent_path' => $path], $client)->locate('198.51.100.5');
            $this->assertSame('North America', $location->continent);
        } finally {
            unlink($path);
        }
    }

    public function test_it_ignores_invalid_continent_data(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'continents-invalid-');
        file_put_contents($path, '{invalid');
        $client = \Mockery::mock(Client::class);
        $client->shouldReceive('get')
            ->once()
            ->andReturn(new Response(200, [], '{"status":"success","countryCode":"CA"}'));

        try {
            $location = $this->service(['continent_path' => $path], $client)->locate('198.51.100.6');
            $this->assertSame('Unknown', $location->continent);
        } finally {
            unlink($path);
        }
    }

    private function service(array $config, Client $client): BoundedIPApi
    {
        return new class($config, $client) extends BoundedIPApi
        {
            private Client $mockClient;

            public array $targetSeen;

            public function __construct(array $config, Client $mockClient)
            {
                $this->mockClient = $mockClient;
                parent::__construct($config);
            }

            protected function makeClient(float $timeout, string $scheme, string $host): Client
            {
                $this->targetSeen = [$scheme, $host];

                return $this->mockClient;
            }
        };
    }
}
