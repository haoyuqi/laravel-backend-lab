<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AdminAccessGatesTest extends TestCase
{
    /**
     * REQ-116-TEST-3: Verify admin_emails parsing and normalization logic via config/app.php.
     */
    public function test_admin_emails_normalization_handles_spaces_and_casing(): void
    {
        $originalEnv = $_ENV['ADMIN_EMAILS'] ?? null;
        $_ENV['ADMIN_EMAILS'] = '  Admin1@Example.com , USER2@DOMAIN.COM , , admin3@domain.com  ';

        try {
            $config = require config_path('app.php');

            $this->assertSame([
                'admin1@example.com',
                'user2@domain.com',
                'admin3@domain.com',
            ], $config['admin_emails']);
        } finally {
            if ($originalEnv !== null) {
                $_ENV['ADMIN_EMAILS'] = $originalEnv;
            } else {
                unset($_ENV['ADMIN_EMAILS']);
            }
        }
    }

    /**
     * REQ-116-TEST-3: Verify Horizon gate authorization with case-insensitivity.
     */
    public function test_horizon_gate_allows_whitelisted_user_case_insensitively(): void
    {
        config(['app.admin_emails' => ['admin@example.com', 'manager@example.com']]);

        $whitelistedUserLower = new User(['email' => 'admin@example.com']);
        $whitelistedUserUpper = new User(['email' => 'Admin@EXAMPLE.COM']);
        $unauthorizedUser = new User(['email' => 'hacker@example.com']);

        $this->assertTrue(Gate::forUser($whitelistedUserLower)->check('viewHorizon'));
        $this->assertTrue(Gate::forUser($whitelistedUserUpper)->check('viewHorizon'));
        $this->assertFalse(Gate::forUser($unauthorizedUser)->check('viewHorizon'));
        $this->assertFalse(Gate::forUser(null)->check('viewHorizon'));
    }

    /**
     * REQ-116-TEST-3: Verify Telescope gate authorization with case-insensitivity.
     */
    public function test_telescope_gate_allows_whitelisted_user_case_insensitively(): void
    {
        config([
            'app.admin_emails' => ['auditor@example.com'],
            'telescope.enabled' => true,
        ]);

        $whitelistedUserLower = new User(['email' => 'auditor@example.com']);
        $whitelistedUserUpper = new User(['email' => 'Auditor@EXAMPLE.COM']);
        $unauthorizedUser = new User(['email' => 'stranger@example.com']);

        $this->assertTrue(Gate::forUser($whitelistedUserLower)->check('viewTelescope'));
        $this->assertTrue(Gate::forUser($whitelistedUserUpper)->check('viewTelescope'));
        $this->assertFalse(Gate::forUser($unauthorizedUser)->check('viewTelescope'));
        $this->assertFalse(Gate::forUser(null)->check('viewTelescope'));
    }
}
