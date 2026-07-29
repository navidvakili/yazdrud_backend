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
        Schema::create('county_projects', function (Blueprint $table) {
            $table->id();
            $table->string('county_id', 50)->unique()->comment('شناسه یکتای شهرستان (مثال: yazd, meybod)');
            $table->string('county_name', 100)->comment('نام فارسی شهرستان');
            $table->integer('road_projects_count')->default(0)->comment('تعداد طرح راه‌سازی');
            $table->integer('housing_units_count')->default(0)->comment('تعداد واحد مسکن');
            $table->integer('urban_plans_count')->default(0)->comment('تعداد طرح تفصیلی');
            $table->integer('road_progress')->default(0)->comment('درصد پیشرفت راه‌سازی و بزرگراه (0-100)');
            $table->integer('housing_progress')->default(0)->comment('درصد پیشرفت مسکن ملی (0-100)');
            $table->integer('urban_progress')->default(0)->comment('درصد پیشرفت شهرسازی و طرح‌های تفصیلی (0-100)');
            $table->boolean('has_active_road_project')->default(false)->comment('آیا پروژه فعال راه‌سازی دارد');
            $table->boolean('has_housing_workshop')->default(false)->comment('آیا کارگاه انبوه‌سازی مسکن ملی دارد');
            $table->text('description')->nullable()->comment('توضیحات شهرستان');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('county_projects');
    }
};
