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
            if (!Schema::hasColumn('term_coupons', 'course_id')) {
                $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete()->after('term_id');
            }
            if (!Schema::hasColumn('term_coupons', 'used_count')) {
                $table->integer('used_count')->default(0)->after('capacity');
            }
            if (!Schema::hasColumn('term_coupons', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('used_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('term_coupons', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropColumn(['course_id', 'used_count', 'is_active']);
        });
    }
};
