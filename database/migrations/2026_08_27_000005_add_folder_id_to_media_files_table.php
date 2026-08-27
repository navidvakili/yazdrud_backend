<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attach media files to virtual folders (media_folders.folder_id).
     *
     * The reference is purely logical — deleting the folder keeps the file
     * on disk and simply clears folder_id to null.
     */
    public function up(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->unsignedBigInteger('folder_id')->nullable()->after('path');
            $table->foreign('folder_id')->references('id')->on('media_folders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->dropForeign(['folder_id']);
            $table->dropColumn('folder_id');
        });
    }
};
