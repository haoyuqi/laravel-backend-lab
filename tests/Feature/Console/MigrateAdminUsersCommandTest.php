<?php

namespace Tests\Feature\Console;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrateAdminUsersCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanupTestData();
        $this->createAdminUsersTable();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        Schema::dropIfExists('admin_users');

        parent::tearDown();
    }

    protected function cleanupTestData(): void
    {
        DB::table('users')->whereIn('email', [
            'migrated@example.com',
            'valid@example.com',
            'duplicate@example.com',
        ])->delete();
    }

    protected function createAdminUsersTable(): void
    {
        Schema::dropIfExists('admin_users');

        Schema::create('admin_users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username', 190)->unique();
            $table->string('password', 60);
            $table->string('name');
            $table->string('avatar')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamps();
        });
    }

    public function test_command_exits_successfully_when_admin_users_table_does_not_exist(): void
    {
        Schema::dropIfExists('admin_users');

        $this->artisan('admin:migrate-users')
            ->expectsOutput("Table 'admin_users' does not exist. Nothing to migrate.")
            ->assertSuccessful();
    }

    public function test_command_exits_successfully_when_admin_users_table_is_empty(): void
    {
        $this->artisan('admin:migrate-users')
            ->expectsOutput("No legacy admin accounts found in 'admin_users'. Nothing to migrate.")
            ->assertSuccessful();
    }

    public function test_command_fails_in_non_interactive_mode_when_admin_users_has_data(): void
    {
        DB::table('admin_users')->insert([
            'username' => 'legacy_admin',
            'password' => Hash::make('secret123'),
            'name' => 'Legacy Admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('admin:migrate-users', ['--no-interaction' => true])
            ->expectsOutput('This command requires interactive user input to assign email addresses.')
            ->assertFailed();
    }

    public function test_command_interactively_migrates_legacy_user_and_preserves_password_hash(): void
    {
        $hashedPassword = Hash::make('admin_secret');

        DB::table('admin_users')->insert([
            'username' => 'super_admin',
            'password' => $hashedPassword,
            'name' => 'Super Admin',
            'remember_token' => 'legacy_token_123',
            'created_at' => now()->subYear(),
            'updated_at' => now()->subYear(),
        ]);

        $this->artisan('admin:migrate-users')
            ->expectsQuestion("Enter a valid email for 'super_admin', [s] to skip, or [d] to discard/delete", 'migrated@example.com')
            ->expectsOutput("Successfully migrated 'super_admin' to <migrated@example.com>.")
            ->assertSuccessful();

        $this->assertDatabaseMissing('admin_users', ['username' => 'super_admin']);

        $migrated = DB::table('users')->where('email', 'migrated@example.com')->first();
        $this->assertNotNull($migrated);
        $this->assertSame('Super Admin', $migrated->name);
        $this->assertSame('legacy_token_123', $migrated->remember_token);
        $this->assertSame($hashedPassword, $migrated->password);

        $this->assertTrue(Auth::attempt([
            'email' => 'migrated@example.com',
            'password' => 'admin_secret',
        ]));
    }

    public function test_command_supports_skipping_account(): void
    {
        DB::table('admin_users')->insert([
            'username' => 'skip_me',
            'password' => Hash::make('password'),
            'name' => 'Skip User',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('admin:migrate-users')
            ->expectsQuestion("Enter a valid email for 'skip_me', [s] to skip, or [d] to discard/delete", 's')
            ->expectsOutput("Skipped account 'skip_me'.")
            ->assertSuccessful();

        $this->assertDatabaseHas('admin_users', ['username' => 'skip_me']);
    }

    public function test_command_supports_discarding_account(): void
    {
        DB::table('admin_users')->insert([
            'username' => 'discard_me',
            'password' => Hash::make('password'),
            'name' => 'Discard User',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('admin:migrate-users')
            ->expectsQuestion("Enter a valid email for 'discard_me', [s] to skip, or [d] to discard/delete", 'd')
            ->expectsConfirmation("Are you sure you want to permanently discard legacy account 'discard_me'?", 'yes')
            ->expectsOutput("Discarded legacy account 'discard_me'.")
            ->assertSuccessful();

        $this->assertDatabaseMissing('admin_users', ['username' => 'discard_me']);
    }

    public function test_command_validates_invalid_email_and_retries(): void
    {
        DB::table('admin_users')->insert([
            'username' => 'retry_user',
            'password' => Hash::make('password'),
            'name' => 'Retry User',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('admin:migrate-users')
            ->expectsQuestion("Enter a valid email for 'retry_user', [s] to skip, or [d] to discard/delete", 'invalid-email')
            ->expectsOutput("Invalid email address: 'invalid-email'.")
            ->expectsQuestion("Enter a valid email for 'retry_user', [s] to skip, or [d] to discard/delete", 'valid@example.com')
            ->expectsOutput("Successfully migrated 'retry_user' to <valid@example.com>.")
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'valid@example.com']);
    }

    public function test_command_handles_duplicate_email_overwrite(): void
    {
        DB::table('users')->insert([
            'name' => 'Old Name',
            'email' => 'duplicate@example.com',
            'password' => Hash::make('old_pass'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_users')->insert([
            'username' => 'duplicate_user',
            'password' => Hash::make('new_pass'),
            'name' => 'New Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('admin:migrate-users')
            ->expectsQuestion("Enter a valid email for 'duplicate_user', [s] to skip, or [d] to discard/delete", 'duplicate@example.com')
            ->expectsChoice(
                "A user with email 'duplicate@example.com' already exists (Name: Old Name). What would you like to do?",
                'Overwrite existing user',
                ['Overwrite existing user', 'Skip this account', 'Enter another email']
            )
            ->expectsOutput("Successfully migrated 'duplicate_user' to <duplicate@example.com>.")
            ->assertSuccessful();

        $updated = DB::table('users')->where('email', 'duplicate@example.com')->first();
        $this->assertSame('New Name', $updated->name);
        $this->assertTrue(Hash::check('new_pass', $updated->password));
    }
}
