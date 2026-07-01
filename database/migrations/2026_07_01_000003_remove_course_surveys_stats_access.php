<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations — remove the 'course-surveys/statistics' submenu
     * and the 'tuts-surveys-stats' module from the backend.
     */
    public function up(): void
    {
        // Remove the "آمار و نمودارهای نظرسنجی" access record
        DB::table('accesses')->where('id', 209)->delete();
    }

    /**
     * Reverse the migrations — re-insert the stats access record.
     */
    public function down(): void
    {
        DB::table('accesses')->updateOrInsert(
            ['id' => 209],
            [
                'parent'     => 12,
                'title'      => 'آمار و نمودارهای نظرسنجی',
                'url'        => '/course-surveys/statistics',
                'icon'       => 'fa fa-bar-chart',
                'roles'      => 'admin|pajouheshikol|pajouheshi|',
                'ordering'   => 6,
                'active'     => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
};
