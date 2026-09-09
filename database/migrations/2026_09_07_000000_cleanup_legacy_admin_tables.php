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
        if (Schema::hasTable('admin_users')) {
            $count = DB::table('admin_users')->count();

            if ($count > 0) {
                throw new RuntimeException(
                    "Migration blocked: 'admin_users' table still contains {$count} legacy account(s). "
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
            'admin_users',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
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
