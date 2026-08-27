<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('site_navigations', function (Blueprint $table) {
            if (!Schema::hasColumn('site_navigations', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('version');
            }
        });

        DB::table('site_navigations')
            ->whereNull('sort_order')
            ->update(['sort_order' => 0]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_navigations', function (Blueprint $table) {
            if (Schema::hasColumn('site_navigations', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });
    }
};
