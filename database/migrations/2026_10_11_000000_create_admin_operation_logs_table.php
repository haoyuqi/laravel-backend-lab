<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_operation_logs', function (Blueprint $table): void {
            $table->id();
            // Snapshots intentionally survive removal of either actor or subject.
            $table->unsignedBigInteger('actor_id');
            $table->string('actor_name');
            $table->string('action', 16);
            $table->string('panel', 32)->default('admin');
            $table->string('action_key', 128)->nullable();
            $table->uuid('operation_id')->nullable()->index();
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_id', 128)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->index(['action', 'id']);
            $table->index(['subject_type', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_operation_logs');
    }
};
