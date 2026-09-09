<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = env('LEGACY_ADMIN_CONNECTION');
        $usersTable = env('LEGACY_ADMIN_USERS_TABLE', 'admin_users');
        $schema = Schema::connection($connection);
        $db = DB::connection($connection);

        if ($schema->hasTable($usersTable)) {
            $count = $db->table($usersTable)->count();

            if ($count > 0) {
                throw new RuntimeException(
                    "Migration blocked: '{$usersTable}' table still contains {$count} legacy account(s). "
                    .'To prevent data loss, please run `php artisan admin:migrate-users` before running migrations.'
                );
            }
        }

        $tables = [
            'admin_operation_log',
            'admin_user_permissions',
            'admin_role_users',
            'admin_role_permissions',
            'admin_role_menu',
            'admin_permissions',
            'admin_roles',
            'admin_menu',
            $usersTable,
        ];

        foreach (array_unique($tables) as $table) {
            $schema->dropIfExists($table);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Legacy encore/laravel-admin tables cannot be restored after cleanup.
        // Restore from database backup if rollback is needed.
    }
};
