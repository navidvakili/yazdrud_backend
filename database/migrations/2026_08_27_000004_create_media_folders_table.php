<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Virtual media folders (ساختار پوشه‌های مجازی رسانه).
     *
     * These folders are ORGANIZATIONAL ONLY — they never map to a physical
     * directory on disk. The media files keep their flat storage path under
     * storage/app/public/media/Y/m/uuid.ext and only reference a folder via
     * media_files.folder_id.
     */
    public function up(): void
    {
        Schema::create('media_folders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('color')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedInteger('ordering')->default(0);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('media_folders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_folders');
    }
};
