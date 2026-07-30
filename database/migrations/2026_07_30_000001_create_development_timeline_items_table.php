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
        Schema::create('development_timeline_items', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255)->comment('عنوان');
            $table->text('description')->nullable()->comment('توضیحات');
            $table->string('year', 50)->comment('سال یا بازه زمانی');
            $table->string('icon', 100)->nullable()->comment('کلاس آیکون (FontAwesome)');
            $table->string('image_url', 500)->nullable()->comment('آدرس تصویر');
            $table->enum('type', ['road', 'urban', 'both'])->default('both')->comment('نوع: راه‌سازی، عمران شهری، هر دو');
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
        Schema::dropIfExists('development_timeline_items');
    }
};
