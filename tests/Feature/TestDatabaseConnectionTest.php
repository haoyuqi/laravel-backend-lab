<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TestDatabaseConnectionTest extends TestCase
{
    public function test_the_dedicated_test_database_is_migrated(): void
    {
        $this->assertSame('testing', config('database.default'));
        $this->assertTrue(Schema::hasTable('users'));
    }
}
