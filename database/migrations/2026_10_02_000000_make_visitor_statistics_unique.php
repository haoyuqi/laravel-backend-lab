<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Retain discarded snapshots for audit/manual recovery, also after rollback.
        if (! Schema::hasTable('visitor_statistics_duplicates')) {
            Schema::create('visitor_statistics_duplicates', function (Blueprint $table) {
                $table->unsignedBigInteger('id')->primary();
                $table->string('type', 20);
                $table->date('date');
                $table->bigInteger('count');
                $table->softDeletes();
                $table->timestamps();
            });
        }

        DB::transaction(function (): void {
            do {
                $groups = DB::table('visitor_statistics')
                    ->select('type', 'date')->groupBy('type', 'date')
                    ->havingRaw('COUNT(*) > 1')->limit(200)->get();

                foreach ($groups as $group) {
                    $rows = DB::table('visitor_statistics')
                        ->where('type', $group->type)->where('date', $group->date);
                    $winner = (clone $rows)->where('count', '>=', 0)
                        ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
                        ->orderByRaw("COALESCE(updated_at, created_at, '1970-01-01 00:00:00') DESC")
                        ->orderByDesc('id')->first();

                    if ($winner === null) {
                        throw new RuntimeException('No valid visitor statistics snapshot for '.$group->type.' on '.$group->date.'. Repair this group before retrying migration.');
                    }

                    $discarded = (clone $rows)->where('id', '<>', $winner->id);
                    // Chunk by id so a large duplicate group does not exhaust memory.
                    (clone $discarded)->chunkById(200, function ($snapshots): void {
                        DB::table('visitor_statistics_duplicates')->insertOrIgnore(
                            $snapshots->map(fn ($snapshot) => (array) $snapshot)->all()
                        );
                    });
                    $discarded->delete();
                }
            } while ($groups->isNotEmpty());
        });

        Schema::table('visitor_statistics', function (Blueprint $table) {
            $table->unique(['type', 'date'], 'visitor_statistics_type_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('visitor_statistics', function (Blueprint $table) {
            $table->dropUnique('visitor_statistics_type_date_unique');
        });
    }
};
