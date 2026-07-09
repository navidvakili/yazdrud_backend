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
        if (!Schema::hasTable('course_surveys')) {
            return;
        }
        Schema::table('course_surveys', function (Blueprint $table) {
            if (!Schema::hasColumn('course_surveys', 'course_id')) {
                $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete()->after('id');
            }
            if (!Schema::hasColumn('course_surveys', 'rating')) {
                $table->tinyInteger('rating')->nullable()->after('phone_number');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('course_surveys')) {
            return;
        }
        Schema::table('course_surveys', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropColumn(['course_id', 'rating']);
        });
    }
};
