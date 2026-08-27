<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add optional title (عنوان) and description (توضیح) metadata to media files.
     *
     * The file-manager widget shows these when present, falling back to the
     * file name when no title has been set.
     */
    public function up(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->string('title')->nullable()->after('name');
            $table->text('description')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->dropColumn(['title', 'description']);
        });
    }
};
