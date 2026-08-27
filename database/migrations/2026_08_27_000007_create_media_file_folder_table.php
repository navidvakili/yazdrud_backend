<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Many-to-many relationship between media files and virtual media folders.
     *
     * Previously a file could belong to exactly one folder (media_files.folder_id).
     * This pivot table allows a file to be registered in several groups at once.
     * The legacy folder_id column is kept for backward compatibility and always
     * mirrors the first (lowest) pivot folder, if any.
     */
    public function up(): void
    {
        Schema::create('media_file_folder', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('media_file_id');
            $table->unsignedBigInteger('media_folder_id');
            $table->timestamps();

            $table->unique(['media_file_id', 'media_folder_id'], 'media_file_folder_unique');

            $table->foreign('media_file_id')
                ->references('id')
                ->on('media_files')
                ->cascadeOnDelete();

            $table->foreign('media_folder_id')
                ->references('id')
                ->on('media_folders')
                ->cascadeOnDelete();
        });

        // Backfill the pivot from the legacy single-folder column so existing
        // files keep their current group membership.
        DB::table('media_files')
            ->whereNotNull('folder_id')
            ->orderBy('id')
            ->eachById(function ($file) {
                DB::table('media_file_folder')->insertOrIgnore([
                    'media_file_id' => $file->id,
                    'media_folder_id' => $file->folder_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_file_folder');
    }
};
