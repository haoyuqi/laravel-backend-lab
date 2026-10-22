<?php

namespace Tests\Feature;

use Tests\TestCase;

class HelpersTest extends TestCase
{
    public function test_is_ip_accepts_ipv4_and_ipv6_and_rejects_invalid_input(): void
    {
        // The helper is loaded by Composer before PHPUnit starts line coverage.
        // Including it again exercises its function_exists guard without redeclaring it.
        include app_path('Libraries/helpers.php');

        $this->assertTrue(is_ip('192.0.2.1'));
        $this->assertTrue(is_ip('2001:db8::1'));
        $this->assertFalse(is_ip('not-an-ip'));
    }
}
