<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Pages\Auth\Login;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class AdminMigrationIntegrationTest extends TestCase
{
    protected array $legacyTables = [
        'admin_operation_log',
        'admin_user_permissions',
        'admin_role_users',
        'admin_role_permissions',
        'admin_role_menu',
        'admin_permissions',
        'admin_roles',
        'admin_menu',
        'admin_users',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Hard isolation assertion: tests must run strictly against dedicated test database
        $this->assertSame('testing', config('database.default'));
        $this->assertNotSame('web', DB::connection()->getDatabaseName());
        $this->assertSame(
            config('database.connections.testing.database'),
            DB::connection()->getDatabaseName()
        );

        $this->cleanupLegacyTables();
    }

    protected function tearDown(): void
    {
        $this->cleanupLegacyTables();
        DB::table('users')->where('email', 'like', '%@example.com')->delete();

        parent::tearDown();
    }

    protected function cleanupLegacyTables(): void
    {
        foreach ($this->legacyTables as $table) {
            Schema::dropIfExists($table);
        }
    }

    protected function createLegacyAdminUsersTable(): void
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

    protected function createAllLegacyTablesFixture(): void
    {
        $this->cleanupLegacyTables();

        $this->createLegacyAdminUsersTable();

        foreach (array_diff($this->legacyTables, ['admin_users']) as $table) {
            Schema::create($table, function (Blueprint $table) {
                $table->increments('id');
                $table->timestamps();
            });
        }
    }

    /**
     * REQ-116-TEST-1: Migrated admin can log in to Filament panel with original plaintext password.
     */
    public function test_migrated_admin_can_log_in_to_filament_with_original_password(): void
    {
        $this->createLegacyAdminUsersTable();

        $plainPassword = 'Pr0d_P@ssw0rd_2026!';
        $legacyBcryptHash = Hash::make($plainPassword);
        $email = 'migrated-admin@example.com';

        DB::table('admin_users')->insert([
            'username' => 'prod_admin',
            'password' => $legacyBcryptHash,
            'name' => 'Production Admin',
            'remember_token' => 'remember_token_abc',
            'created_at' => now()->subMonths(6),
            'updated_at' => now()->subMonths(6),
        ]);

        // Run interactive migration command
        $this->artisan('admin:migrate-users')
            ->expectsQuestion("Enter a valid email for 'prod_admin', [s] to skip, or [d] to discard/delete", $email)
            ->expectsOutput("Successfully migrated 'prod_admin' to <{$email}>.")
            ->assertSuccessful();

        // 1. Assert record deleted from admin_users
        $this->assertDatabaseMissing('admin_users', ['username' => 'prod_admin']);

        // 2. Assert record created in users table with exact hash preserved
        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertSame('Production Admin', $user->name);
        $this->assertSame(
            $legacyBcryptHash,
            $user->password,
            'Raw bcrypt password hash must be preserved identically without double-hashing.'
        );

        // 3. Test authenticating via Filament login with original plaintext password
        Livewire::test(Login::class)
            ->fillForm([
                'email' => $email,
                'password' => $plainPassword,
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user);

        // 4. Test authenticating with wrong password is rejected
        auth()->logout();
        Livewire::test(Login::class)
            ->fillForm([
                'email' => $email,
                'password' => 'wrong_password_attempt',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    /**
     * REQ-116-TEST-2: Complete end-to-end upgrade lifecycle from blocking to schema cleanup.
     */
    public function test_complete_migration_lifecycle_from_blocking_to_cleanup(): void
    {
        // Step 1: Set up full legacy schema fixture with 2 active legacy accounts
        $this->createAllLegacyTablesFixture();

        $plainPassword = 'Lifecycle_P@ssword!';
        $legacyHash = Hash::make($plainPassword);
        $emailOne = 'lifecycle-one@example.com';

        DB::table('admin_users')->insert([
            [
                'username' => 'lifecycle_one',
                'password' => $legacyHash,
                'name' => 'Lifecycle Admin One',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'username' => 'lifecycle_two',
                'password' => $legacyHash,
                'name' => 'Lifecycle Admin Two',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Step 2: Delete cleanup migration record from migrations table to simulate pending migration
        DB::table('migrations')->where('migration', 'like', '%cleanup_legacy_admin_tables%')->delete();

        // Step 3: Run migrate and assert it is blocked by RuntimeException because 2 accounts exist
        try {
            $this->artisan('migrate');
            $this->fail('Expected RuntimeException blocking migration was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(
                "Migration blocked: 'admin_users' table still contains 2 legacy account(s).",
                $e->getMessage()
            );
        }

        // Assert all 9 legacy tables still exist after blocked migration
        foreach ($this->legacyTables as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Legacy table '{$table}' should still exist after blocked migration."
            );
        }

        // Step 4: Run admin:migrate-users, migrate account one, and skip account two
        $this->artisan('admin:migrate-users')
            ->expectsQuestion("Enter a valid email for 'lifecycle_one', [s] to skip, or [d] to discard/delete", $emailOne)
            ->expectsOutput("Successfully migrated 'lifecycle_one' to <{$emailOne}>.")
            ->expectsQuestion("Enter a valid email for 'lifecycle_two', [s] to skip, or [d] to discard/delete", 's')
            ->expectsOutput("Skipped account 'lifecycle_two'.")
            ->expectsOutput("1 legacy account(s) remain in 'admin_users'.")
            ->assertSuccessful();

        $this->assertSame(1, DB::table('admin_users')->count());

        // Step 5: Assert migrate is STILL blocked because 1 skipped account remains
        try {
            $this->artisan('migrate');
            $this->fail('Expected RuntimeException blocking migration was not thrown for remaining skipped account.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(
                "Migration blocked: 'admin_users' table still contains 1 legacy account(s).",
                $e->getMessage()
            );
        }

        // Step 6: Run admin:migrate-users again to discard the remaining skipped account
        $this->artisan('admin:migrate-users')
            ->expectsQuestion("Enter a valid email for 'lifecycle_two', [s] to skip, or [d] to discard/delete", 'd')
            ->expectsConfirmation("Are you sure you want to permanently discard legacy account 'lifecycle_two'?", 'yes')
            ->expectsOutput("Discarded legacy account 'lifecycle_two'.")
            ->expectsOutput('All legacy admin accounts have been migrated or discarded.')
            ->assertSuccessful();

        $this->assertSame(0, DB::table('admin_users')->count());

        // Step 7: Run migrate again, now it should succeed and drop all 9 legacy tables
        $this->artisan('migrate')->assertSuccessful();

        // Assert all 9 legacy tables are now dropped
        foreach ($this->legacyTables as $table) {
            $this->assertFalse(
                Schema::hasTable($table),
                "Legacy table '{$table}' should have been dropped after successful cleanup migration."
            );
        }

        // Assert migrated user is intact in users table
        $this->assertDatabaseHas('users', [
            'email' => $emailOne,
            'name' => 'Lifecycle Admin One',
        ]);
    }
}
