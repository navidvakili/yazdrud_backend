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
        Schema::table('courses', function (Blueprint $table) {
            $table->json('days_of_week')->nullable()->comment('روزهای برگزاری در هفته');
            $table->string('course_time', 255)->nullable()->comment('ساعت برگزاری دوره');
            $table->string('location', 500)->nullable()->comment('مکان برگزاری دوره');
            $table->text('prerequisites')->nullable()->comment('پیش‌نیازهای دوره (هر خط یک مورد)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['days_of_week', 'course_time', 'location', 'prerequisites']);
        });
    }
};
