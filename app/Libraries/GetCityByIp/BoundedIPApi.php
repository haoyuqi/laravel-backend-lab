<?php

namespace App\Libraries\GetCityByIp;

use GuzzleHttp\Client;
use RuntimeException;
use Torann\GeoIP\Services\AbstractService;

class BoundedIPApi extends AbstractService
{
    public function locate($ip)
    {
        $key = $this->config('key');
        $scheme = $key && $this->config('secure', false) ? 'https' : 'http';
        $host = $key ? 'pro.ip-api.com' : 'ip-api.com';
        $timeout = max(1, min((float) $this->config('timeout', 3), 5));
        $client = $this->makeClient($timeout, $scheme, $host);

        $query = [
            'fields' => 49663,
            'lang' => $this->config('lang', 'en'),
        ];

        if ($key) {
            $query['key'] = $key;
        }

        try {
            $response = $client->get('json/'.$ip, ['query' => $query]);
        } catch (\Throwable) {
            // Avoid forwarding provider exceptions that may include the IP or API key.
            throw new RuntimeException('GeoIP provider request failed.');
        }

        try {
            $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('GeoIP provider returned invalid JSON.', previous: $exception);
        }

        if ($response->getStatusCode() !== 200 || ! is_array($data) || ($data['status'] ?? null) !== 'success') {
            throw new RuntimeException('GeoIP provider request failed.');
        }

        $countryCode = $data['countryCode'] ?? null;
        $continents = $this->continents();

        return $this->hydrate([
            'ip' => $ip,
            'iso_code' => $countryCode,
            'country' => $data['country'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['region'] ?? null,
            'state_name' => $data['regionName'] ?? null,
            'postal_code' => $data['zip'] ?? null,
            'lat' => $data['lat'] ?? null,
            'lon' => $data['lon'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'continent' => $continents[$countryCode] ?? 'Unknown',
        ]);
    }

    protected function makeClient(float $timeout, string $scheme, string $host): Client
    {
        return new Client([
            'base_uri' => $scheme.'://'.$host.'/',
            'timeout' => $timeout,
            'connect_timeout' => min(2, $timeout),
            'http_errors' => false,
        ]);
    }

    private function continents(): array
    {
        $path = $this->config('continent_path');

        if (! is_string($path) || ! is_file($path)) {
            return [];
        }

        $continents = json_decode((string) file_get_contents($path), true);

        return is_array($continents) ? $continents : [];
    }
}
