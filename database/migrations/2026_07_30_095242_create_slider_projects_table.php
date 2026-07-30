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
        Schema::create('slider_projects', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255)->comment('عنوان پروژه');
            $table->text('description')->nullable()->comment('توضیحات پروژه');
            $table->json('project_data')->comment('داده‌های کامل پروژه (Slides + Layers)');
            $table->boolean('is_active')->default(false)->comment('فعال برای نمایش عمومی');
            $table->integer('sort_order')->default(0)->comment('ترتیب نمایش');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slider_projects');
    }
};
