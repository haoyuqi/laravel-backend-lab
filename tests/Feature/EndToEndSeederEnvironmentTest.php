<?php

namespace Tests\Feature;

use Database\Seeders\E2EDataSeeder as EndToEndSeeder;
use Tests\TestCase;

class EndToEndSeederEnvironmentTest extends TestCase
{
    public function test_seeder_populates_fixtures_in_the_testing_environment(): void
    {
        $this->seed(EndToEndSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'admin@example.com']);
        $this->assertDatabaseHas('visitors', ['ip' => '127.0.0.1']);
        $this->assertDatabaseHas('black_lists', ['ip' => '127.0.0.99']);
    }

    public function test_seeder_is_a_no_op_outside_local_and_testing(): void
    {
        $original = $this->app['env'];

        try {
            $this->app['env'] = 'production';

            $this->artisan('db:seed', ['--class' => EndToEndSeeder::class, '--force' => true])
                ->expectsOutputToContain('Skipping the end-to-end seeder')
                ->assertSuccessful();
        } finally {
            $this->app['env'] = $original;
        }

        $this->assertDatabaseMissing('users', ['email' => 'admin@example.com']);
        $this->assertDatabaseMissing('visitors', ['ip' => '127.0.0.1']);
    }
}
