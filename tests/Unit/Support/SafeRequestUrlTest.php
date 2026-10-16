<?php

namespace Tests\Unit\Support;

use App\Support\SafeRequestUrl;
use PHPUnit\Framework\TestCase;

class SafeRequestUrlTest extends TestCase
{
    public function test_it_keeps_only_the_bounded_path(): void
    {
        $this->assertSame('/orders/42', SafeRequestUrl::sanitize('https://example.test/orders/42?token=private#section'));
        $this->assertSame('/path', SafeRequestUrl::sanitize('/path?query=private'));
        $this->assertSame('/', SafeRequestUrl::sanitize('https://example.test?query=private'));
    }

    public function test_it_bounds_the_path_and_removes_control_characters(): void
    {
        $this->assertSame('/ab', SafeRequestUrl::sanitize("/a\nbc", 3));
        $this->assertSame('/abc', SafeRequestUrl::sanitize('/abcdef', 4));
    }
}
