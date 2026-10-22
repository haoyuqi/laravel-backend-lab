<?php

namespace Tests\Unit\Support;

use App\Libraries\GetCityByIp\FreeAPI;
use App\Libraries\GetCityByIp\GeoIP;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class GeoIpProvidersTest extends TestCase
{
    public function test_free_provider_returns_a_city_for_a_successful_ip_lookup(): void
    {
        $client = \Mockery::mock(ClientInterface::class);
        $client->shouldReceive('request')
            ->once()
            ->with('GET', 'http://ip-api.com/json/198.51.100.20?lang=zh-CN', [
                'timeout' => 3,
                'connect_timeout' => 1,
            ])
            ->andReturn(new Response(200, [], '{"status":"success","city":"Shenzhen"}'));

        $this->assertSame('Shenzhen', (new FreeAPI($client))->getCity('198.51.100.20'));
    }

    public function test_free_provider_returns_an_empty_city_for_an_invalid_ip_or_failed_response(): void
    {
        $client = \Mockery::mock(ClientInterface::class);
        $client->shouldReceive('request')
            ->once()
            ->andReturn(new Response(503, [], '{"status":"fail"}'));
        Log::shouldReceive('error')->times(5);

        $provider = new FreeAPI($client);

        $this->assertSame('', $provider->getCity('not-an-ip'));
        $this->assertSame('', $provider->getCity('198.51.100.21'));
    }

    public function test_geoip_provider_rejects_invalid_ips_without_calling_the_client(): void
    {
        $client = \Mockery::mock(ClientInterface::class);
        $client->shouldNotReceive('request');
        Log::shouldReceive('error')->twice();

        $this->assertSame('', (new GeoIP($client))->getCity('invalid'));
    }
}
