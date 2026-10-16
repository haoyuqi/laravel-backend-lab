<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class BlackListLogsAddIndex extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('black_list_logs', function (Blueprint $table) {
            $table->index('black_list_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('black_list_logs', function (Blueprint $table) {
            $table->dropIndex(['black_list_id']);
        });
    }
}
