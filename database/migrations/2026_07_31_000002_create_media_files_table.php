<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Metadata table for uploaded media files.
     *
     * Listing files becomes an indexed SQL query instead of walking the
     * whole disk, so it stays fast as the media library grows. The table
     * is kept in sync by the controller (upload/destroy) and can be
     * backfilled from disk at any time with `php artisan media:sync`.
     */
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->string('name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->index(['name', 'uploaded_at', 'size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
