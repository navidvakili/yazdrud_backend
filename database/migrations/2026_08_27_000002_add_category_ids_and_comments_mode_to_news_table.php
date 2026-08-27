<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->json('category_ids')->nullable()->after('category_id');
            $table->string('comments_mode', 20)->default('approval')->after('comments_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn(['category_ids', 'comments_mode']);
        });
    }
};
