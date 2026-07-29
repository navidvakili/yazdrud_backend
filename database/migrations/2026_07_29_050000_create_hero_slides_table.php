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
        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            $table->string('tag', 100)->comment('برچسب دسته‌بندی');
            $table->string('title', 255)->comment('عنوان اصلی اسلاید');
            $table->text('subtitle')->comment('توضیحات اسلاید');
            $table->string('badge', 255)->comment('متن آمار/نشان');
            $table->string('badge_icon', 100)->comment('آیکون FontAwesome');
            $table->string('bg_image', 255)->nullable()->comment('مسیر تصویر پس‌زمینه');
            $table->string('primary_cta_text', 255)->comment('متن دکمه اصلی');
            $table->string('primary_cta_target', 100)->comment('هدف ناوبری دکمه اصلی');
            $table->string('secondary_cta_text', 255)->comment('متن دکمه فرعی');
            $table->string('secondary_cta_target', 100)->comment('هدف ناوبری دکمه فرعی');
            $table->integer('sort_order')->default(0)->comment('ترتیب نمایش');
            $table->boolean('is_active')->default(true)->comment('فعال/غیرفعال');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
    }
};
