<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEXES = [
        'visitors' => 'visitors_retention_created_at_index',
        'visitor_logs' => 'visitor_logs_retention_created_at_index',
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $tableName => $indexName) {
            if (! Schema::hasIndex($tableName, $indexName) && ! Schema::hasIndex($tableName, ['created_at'])) {
                Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                    $table->index('created_at', $indexName);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $tableName => $indexName) {
            if (Schema::hasIndex($tableName, $indexName)) {
                Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                    $table->dropIndex($indexName);
                });
            }
        }
    }
};
