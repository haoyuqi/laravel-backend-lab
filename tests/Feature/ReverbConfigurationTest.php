<?php

namespace Tests\Feature;

use Illuminate\Support\Env;
use Tests\TestCase;

class ReverbConfigurationTest extends TestCase
{
    public function test_allowed_origins_and_rate_limiting_use_secure_defaults(): void
    {
        $config = $this->loadConfig(null, null);
        $app = $config['apps']['apps'][0];

        $this->assertSame(['*'], $app['allowed_origins']);
        $this->assertTrue($app['rate_limiting']['enabled']);
    }

    public function test_allowed_origins_parse_a_comma_separated_list(): void
    {
        $config = $this->loadConfig(' https://app.example.test , https://admin.example.test ', null);

        $this->assertSame(
            ['https://app.example.test', 'https://admin.example.test'],
            $config['apps']['apps'][0]['allowed_origins'],
        );
    }

    public function test_empty_allowed_origins_deny_every_origin(): void
    {
        $config = $this->loadConfig('', null);

        $this->assertSame([], $config['apps']['apps'][0]['allowed_origins']);
    }

    public function test_rate_limiting_can_be_disabled_explicitly(): void
    {
        $config = $this->loadConfig(null, 'false');

        $this->assertFalse($config['apps']['apps'][0]['rate_limiting']['enabled']);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadConfig(?string $origins, ?string $rateLimiting): array
    {
        $repository = Env::getRepository();
        $previous = [
            'REVERB_ALLOWED_ORIGINS' => $repository->get('REVERB_ALLOWED_ORIGINS'),
            'REVERB_APP_RATE_LIMITING_ENABLED' => $repository->get('REVERB_APP_RATE_LIMITING_ENABLED'),
        ];

        try {
            $this->applyEnv($repository, 'REVERB_ALLOWED_ORIGINS', $origins);
            $this->applyEnv($repository, 'REVERB_APP_RATE_LIMITING_ENABLED', $rateLimiting);

            return require config_path('reverb.php');
        } finally {
            foreach ($previous as $key => $value) {
                $this->applyEnv($repository, $key, $value);
            }
        }
    }

    private function applyEnv(mixed $repository, string $key, ?string $value): void
    {
        if ($value === null) {
            $repository->clear($key);

            return;
        }

        $repository->set($key, $value);
    }
}
