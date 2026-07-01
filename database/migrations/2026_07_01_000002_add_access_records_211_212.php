<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Record 211: مدیریت نشست‌های فعال
        DB::table('accesses')->updateOrInsert(
            ['id' => 211],
            [
                'parent'     => 'top',
                'title'      => 'مدیریت نشست‌های فعال',
                'url'        => '/admin-sessions',
                'icon'       => 'fa fa-globe',
                'roles'      => 'admin',
                'ordering'   => 100,
                'active'     => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Record 212: مدیریت و شرایط بن خرید
        DB::table('accesses')->updateOrInsert(
            ['id' => 212],
            [
                'parent'     => 12,
                'title'      => 'مدیریت و شرایط بن خرید',
                'url'        => '/tuts/vouchers',
                'icon'       => 'fa fa-dollar',
                'roles'      => 'admin|pajouheshikol|pajouheshi|',
                'ordering'   => 5,
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
        DB::table('accesses')->whereIn('id', [211, 212])->delete();
    }
};
