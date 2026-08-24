<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AddVisitorMenuInAdmin extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::beginTransaction();
        try {
            $admin_menu_max_order = DB::table('admin_menu')->max('order');
            $admin_menu_id = DB::table('admin_menu')->insertGetId([
                'parent_id' => 0,
                'title' => '访问记录',
                'icon' => 'fa-folder-open',
                'uri' => 'visitor',
                'permission' => null,
                'order' => $admin_menu_max_order + 1,
                'updated_at' => now(),
                'created_at' => now(),
            ]);

            // role is Administrator
            DB::table('admin_role_menu')->insert(['menu_id' => $admin_menu_id, 'role_id' => 1]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::beginTransaction();
        try {
            $res = DB::table('admin_menu')->where(['title' => '访问记录', 'uri' => 'visitor'])->first();
            if (filled($res)) {
                DB::table('admin_menu')->where(['id' => $res->id])->delete();
                DB::table('admin_role_menu')->where(['menu_id' => $res->id])->delete();
                DB::commit();
            }

        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
        }
    }
}
