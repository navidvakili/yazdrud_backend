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
        Schema::table('development_timeline_items', function (Blueprint $table) {
            // Add new columns
            $table->string('value', 255)->nullable()->after('icon')->comment('مقدار');
            $table->string('value_index', 255)->nullable()->after('value')->comment('اندیس مقدار');

            // Remove old columns no longer needed
            $table->dropColumn(['description', 'year', 'image_url', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('development_timeline_items', function (Blueprint $table) {
            $table->dropColumn(['value', 'value_index']);

            $table->text('description')->nullable()->comment('توضیحات');
            $table->string('year', 50)->comment('سال یا بازه زمانی');
            $table->string('image_url', 500)->nullable()->comment('آدرس تصویر');
            $table->enum('type', ['road', 'urban', 'both'])->default('both')->comment('نوع: راه‌سازی، عمران شهری، هر دو');
        });
    }
};
