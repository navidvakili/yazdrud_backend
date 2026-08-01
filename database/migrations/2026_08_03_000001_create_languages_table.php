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
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique()->comment('کد زبان (fa, en, ar, ...)');
            $table->string('name', 100)->comment('نام زبان به زبان خودش (فارسی, English, العربية)');
            $table->string('name_en', 100)->nullable()->comment('نام زبان به انگلیسی');
            $table->enum('dir', ['rtl', 'ltr'])->default('rtl')->comment('جهت نوشتار');
            $table->boolean('is_active')->default(true)->comment('فعال برای نمایش');
            $table->boolean('is_default')->default(false)->comment('زبان پیش‌فرض');
            $table->integer('ordering')->default(0)->comment('ترتیب نمایش');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
