<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A slug only needs to be unique WITHIN one language — the same slug is
     * allowed to exist once per language, since each language variant of a
     * page is now an independent record (see the Page Builder language fix).
     *
     * `translation_group` links language variants of "the same" page together
     * (set when a page is duplicated into another language) — purely
     * informational, no foreign key, so deleting one variant never cascades
     * to its siblings.
     */
    public function up(): void
    {
        Schema::table('smart_pages', function (Blueprint $table) {
            $table->dropUnique('smart_pages_slug_unique');
            $table->unique(['slug', 'language']);
            $table->uuid('translation_group')->nullable()->after('language')->index();
        });
    }

    public function down(): void
    {
        Schema::table('smart_pages', function (Blueprint $table) {
            $table->dropColumn('translation_group');
            $table->dropUnique(['slug', 'language']);
            $table->unique('slug');
        });
    }
};
