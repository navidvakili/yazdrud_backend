<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the language column to all content tables.
     * Every content record belongs to exactly one language (default: fa).
     */
    public function up(): void
    {
        $tables = [
            'news',
            'news_categories',
            'county_projects',
            'development_timeline_items',
            'slider_projects',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('language', 10)->default('fa')->index()->after('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'news',
            'news_categories',
            'county_projects',
            'development_timeline_items',
            'slider_projects',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('language');
            });
        }
    }
};
