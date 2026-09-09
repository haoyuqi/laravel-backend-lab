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

        // In MySQL, DDL statements (Schema::create / dropIfExists) trigger an implicit commit,
        // which commits the transaction started by RefreshDatabase and leaves MySQL in
        // autocommit mode while Laravel's connection transaction counter remains at 1.
        // Re-synchronize the transaction state so nested transactions and savepoints work properly.
        if (DB::connection()->getDriverName() === 'mysql') {
            while (DB::connection()->transactionLevel() > 0) {
                DB::connection()->rollBack();
            }
            DB::connection()->beginTransaction();
        }
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
            'fresh@example.com',
            'tx@example.com',
            'keep_me@example.com',
            'custom@example.com',
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
            ->expectsConfirmation("WARNING: This will overwrite the password and name for existing user 'Old Name' <duplicate@example.com>. Are you sure you want to proceed?", 'yes')
            ->expectsOutput("Successfully migrated 'duplicate_user' to <duplicate@example.com>.")
            ->assertSuccessful();

        $updated = DB::table('users')->where('email', 'duplicate@example.com')->first();
        $this->assertSame('New Name', $updated->name);
        $this->assertTrue(Hash::check('new_pass', $updated->password));
    }

    public function test_command_cancels_overwrite_when_confirmation_declined(): void
    {
        DB::table('users')->insert([
            'name' => 'Original Name',
            'email' => 'keep_me@example.com',
            'password' => Hash::make('original_pass'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_users')->insert([
            'username' => 'cancel_overwrite_user',
            'password' => Hash::make('attacker_pass'),
            'name' => 'Attacker Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('admin:migrate-users')
            ->expectsQuestion("Enter a valid email for 'cancel_overwrite_user', [s] to skip, or [d] to discard/delete", 'keep_me@example.com')
            ->expectsChoice(
                "A user with email 'keep_me@example.com' already exists (Name: Original Name). What would you like to do?",
                'Overwrite existing user',
                ['Overwrite existing user', 'Skip this account', 'Enter another email']
            )
            ->expectsConfirmation("WARNING: This will overwrite the password and name for existing user 'Original Name' <keep_me@example.com>. Are you sure you want to proceed?", 'no')
            ->expectsOutput('Overwrite cancelled.')
            ->expectsQuestion("Enter a valid email for 'cancel_overwrite_user', [s] to skip, or [d] to discard/delete", 's')
            ->expectsOutput("Skipped account 'cancel_overwrite_user'.")
            ->assertSuccessful();

        $original = DB::table('users')->where('email', 'keep_me@example.com')->first();
        $this->assertSame('Original Name', $original->name);
        $this->assertTrue(Hash::check('original_pass', $original->password));
        $this->assertDatabaseHas('admin_users', ['username' => 'cancel_overwrite_user']);
    }

    public function test_command_warns_and_retries_on_empty_or_whitespace_input(): void
    {
        DB::table('admin_users')->insert([
            'username' => 'whitespace_user',
            'password' => Hash::make('password'),
            'name' => 'Whitespace User',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('admin:migrate-users')
            ->expectsQuestion("Enter a valid email for 'whitespace_user', [s] to skip, or [d] to discard/delete", '   ')
            ->expectsOutput('Input cannot be empty. Please enter an email, "s" to skip, or "d" to discard.')
            ->expectsQuestion("Enter a valid email for 'whitespace_user', [s] to skip, or [d] to discard/delete", 'valid@example.com')
            ->expectsOutput("Successfully migrated 'whitespace_user' to <valid@example.com>.")
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'valid@example.com']);
    }

    public function test_command_cancels_discard_when_confirmation_declined(): void
    {
        DB::table('admin_users')->insert([
            'username' => 'cancel_discard_user',
            'password' => Hash::make('password'),
            'name' => 'Cancel Discard User',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('admin:migrate-users')
            ->expectsQuestion("Enter a valid email for 'cancel_discard_user', [s] to skip, or [d] to discard/delete", 'd')
            ->expectsConfirmation("Are you sure you want to permanently discard legacy account 'cancel_discard_user'?", 'no')
            ->expectsQuestion("Enter a valid email for 'cancel_discard_user', [s] to skip, or [d] to discard/delete", 's')
            ->expectsOutput("Skipped account 'cancel_discard_user'.")
            ->assertSuccessful();

        $this->assertDatabaseHas('admin_users', ['username' => 'cancel_discard_user']);
    }

    public function test_command_handles_duplicate_email_skip_choice(): void
    {
        DB::table('users')->insert([
            'name' => 'Existing User',
            'email' => 'duplicate@example.com',
            'password' => Hash::make('existing_pass'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_users')->insert([
            'username' => 'skip_dup_user',
            'password' => Hash::make('new_pass'),
            'name' => 'Skip Dup User',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('admin:migrate-users')
            ->expectsQuestion("Enter a valid email for 'skip_dup_user', [s] to skip, or [d] to discard/delete", 'duplicate@example.com')
            ->expectsChoice(
                "A user with email 'duplicate@example.com' already exists (Name: Existing User). What would you like to do?",
                'Skip this account',
                ['Overwrite existing user', 'Skip this account', 'Enter another email']
            )
            ->expectsOutput("Skipped account 'skip_dup_user'.")
            ->assertSuccessful();

        $this->assertDatabaseHas('admin_users', ['username' => 'skip_dup_user']);
        $existing = DB::table('users')->where('email', 'duplicate@example.com')->first();
        $this->assertSame('Existing User', $existing->name);
    }

    public function test_command_handles_duplicate_email_retry_choice(): void
    {
        DB::table('users')->insert([
            'name' => 'Existing User',
            'email' => 'duplicate@example.com',
            'password' => Hash::make('existing_pass'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_users')->insert([
            'username' => 'retry_dup_user',
            'password' => Hash::make('new_pass'),
            'name' => 'Retry Dup User',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('admin:migrate-users')
            ->expectsQuestion("Enter a valid email for 'retry_dup_user', [s] to skip, or [d] to discard/delete", 'duplicate@example.com')
            ->expectsChoice(
                "A user with email 'duplicate@example.com' already exists (Name: Existing User). What would you like to do?",
                'Enter another email',
                ['Overwrite existing user', 'Skip this account', 'Enter another email']
            )
            ->expectsQuestion("Enter a valid email for 'retry_dup_user', [s] to skip, or [d] to discard/delete", 'fresh@example.com')
            ->expectsOutput("Successfully migrated 'retry_dup_user' to <fresh@example.com>.")
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'fresh@example.com', 'name' => 'Retry Dup User']);
        $this->assertDatabaseMissing('admin_users', ['username' => 'retry_dup_user']);
    }

    public function test_command_rolls_back_transaction_on_failure(): void
    {
        DB::table('admin_users')->insert([
            'username' => 'tx_fail_user',
            'password' => Hash::make('password'),
            'name' => 'Tx Fail User',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $shouldFail = true;
        DB::listen(function ($query) use (&$shouldFail) {
            if ($shouldFail && str_contains(strtolower($query->sql), 'admin_users') && str_contains(strtolower($query->sql), 'delete')) {
                $shouldFail = false;
                throw new \RuntimeException('Simulated delete failure during migration transaction');
            }
        });

        try {
            $this->artisan('admin:migrate-users')
                ->expectsQuestion("Enter a valid email for 'tx_fail_user', [s] to skip, or [d] to discard/delete", 'tx@example.com');
            $this->fail('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated delete failure during migration transaction', $e->getMessage());
        }

        // Both tables must roll back: admin_users retains the user, and users does not persist the user
        $this->assertDatabaseHas('admin_users', ['username' => 'tx_fail_user']);
        $this->assertDatabaseMissing('users', ['email' => 'tx@example.com']);
    }
}
