<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['visitors', 'visitor_logs'] as $tableName) {
            if (! Schema::hasIndex($tableName, ['created_at'])) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->index('created_at');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['visitors', 'visitor_logs'] as $tableName) {
            if (Schema::hasIndex($tableName, ['created_at'])) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropIndex(['created_at']);
                });
            }
        }
    }
};
