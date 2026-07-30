<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->boolean('is_photo_report')->default(false)->after('comments_enabled');
            $table->json('photo_report_images')->nullable()->after('is_photo_report');
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn(['is_photo_report', 'photo_report_images']);
        });
    }
};
