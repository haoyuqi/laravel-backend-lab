<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['visitor_logs', 'black_list_logs'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'url')) {
                throw new RuntimeException("The {$tableName}.url column is required before this migration.");
            }

            // Keep each DDL statement independently repeatable: MySQL may
            // commit the first table alteration before a later one fails.
            Schema::table($tableName, function (Blueprint $table) {
                $table->text('url')->change();
            });

            if (! Schema::hasColumn($tableName, 'event_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->uuid('event_id')->nullable();
                });
            }

            if (! Schema::hasIndex($tableName, ['event_id'], 'unique')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unique('event_id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['visitor_logs', 'black_list_logs'] as $table) {
            if (Schema::hasColumn($table, 'url') && DB::table($table)->whereRaw('length(url) > 255')->exists()) {
                throw new RuntimeException('Cannot roll back request log URLs while values exceed 255 characters.');
            }
        }

        foreach (['visitor_logs', 'black_list_logs'] as $tableName) {
            if (Schema::hasColumn($tableName, 'event_id')) {
                if (Schema::hasIndex($tableName, ['event_id'], 'unique')) {
                    Schema::table($tableName, function (Blueprint $table) {
                        $table->dropUnique(['event_id']);
                    });
                }

                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('event_id');
                });
            }

            if (Schema::hasColumn($tableName, 'url')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('url', 255)->change();
                });
            }
        }
    }
};
