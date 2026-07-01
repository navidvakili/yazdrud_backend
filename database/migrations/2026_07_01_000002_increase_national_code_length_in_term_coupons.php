<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('term_coupons', function (Blueprint $table) {
            if (Schema::hasColumn('term_coupons', 'national_code')) {
                $table->string('national_code', 1000)->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('term_coupons', function (Blueprint $table) {
            if (Schema::hasColumn('term_coupons', 'national_code')) {
                $table->string('national_code', 20)->nullable()->change();
            }
        });
    }
};
