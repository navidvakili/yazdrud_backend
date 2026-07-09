<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations — add installment management menu item under Tuts.
     */
    public function up(): void
    {
        DB::table('accesses')->updateOrInsert(
            ['id' => 213],
            [
                'parent'     => 12,
                'title'      => 'مدیریت اقساط بن‌ها',
                'url'        => '/tuts-installments',
                'icon'       => 'fa fa-credit-card',
                'roles'      => 'admin|',
                'ordering'   => 6,
                'active'     => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('accesses')->where('id', 213)->delete();
    }
};
