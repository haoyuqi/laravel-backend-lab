<?php

namespace Tests\Feature;

use Illuminate\Support\Env;
use Tests\TestCase;

class VisitorConfigurationTest extends TestCase
{
    public function test_retention_days_defaults_to_thirty_when_unset_or_blank(): void
    {
        $this->assertSame(30, $this->loadRetentionDays(null));
        $this->assertSame(30, $this->loadRetentionDays(''));
        $this->assertSame(30, $this->loadRetentionDays('   '));
    }

    public function test_retention_days_parses_explicit_values(): void
    {
        $this->assertSame(45, $this->loadRetentionDays('45'));
        $this->assertSame(45, $this->loadRetentionDays(' 45 '));
        $this->assertSame(0, $this->loadRetentionDays('0'));
        $this->assertSame('1.5', $this->loadRetentionDays('1.5'));
        $this->assertSame('30abc', $this->loadRetentionDays('30abc'));
        $this->assertSame('abc', $this->loadRetentionDays('abc'));
        $this->assertFalse($this->loadRetentionDays('false'));
    }

    private function loadRetentionDays(?string $value): mixed
    {
        $repository = Env::getRepository();
        $previous = $repository->get('VISITOR_RETENTION_DAYS');

        try {
            $this->applyEnv($repository, 'VISITOR_RETENTION_DAYS', $value);

            return (require config_path('visitor.php'))['retention_days'];
        } finally {
            $this->applyEnv($repository, 'VISITOR_RETENTION_DAYS', $previous);
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
