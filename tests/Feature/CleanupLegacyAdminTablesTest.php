<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class CleanupLegacyAdminTablesTest extends TestCase
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

    protected function tearDown(): void
    {
        foreach ($this->legacyTables as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    protected function createLegacyTablesFixture(): void
    {
        foreach ($this->legacyTables as $table) {
            Schema::dropIfExists($table);
            Schema::create($table, function (Blueprint $t) {
                $t->increments('id');
                $t->timestamps();
            });
        }
    }

    public function test_migration_throws_runtime_exception_when_admin_users_has_records(): void
    {
        $this->createLegacyTablesFixture();

        DB::table('admin_users')->insert([
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_09_07_000000_cleanup_legacy_admin_tables.php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Migration blocked: 'admin_users' table still contains 1 legacy account(s).");

        $migration->up();
    }

    public function test_migration_drops_all_nine_legacy_tables_when_admin_users_is_empty(): void
    {
        $this->createLegacyTablesFixture();

        $this->assertSame(0, DB::table('admin_users')->count());

        $migration = require database_path('migrations/2026_09_07_000000_cleanup_legacy_admin_tables.php');
        $migration->up();

        foreach ($this->legacyTables as $table) {
            $this->assertFalse(
                Schema::hasTable($table),
                "Failed asserting that legacy table '{$table}' was dropped."
            );
        }
    }

    public function test_migration_succeeds_when_admin_users_does_not_exist(): void
    {
        foreach ($this->legacyTables as $table) {
            Schema::dropIfExists($table);
        }

        $this->assertFalse(Schema::hasTable('admin_users'));

        $migration = require database_path('migrations/2026_09_07_000000_cleanup_legacy_admin_tables.php');
        $migration->up();

        $this->assertTrue(true);
    }
}
