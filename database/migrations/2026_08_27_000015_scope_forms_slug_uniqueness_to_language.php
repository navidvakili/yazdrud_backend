<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Same fix as smart_pages: a slug only needs to be unique WITHIN one
     * language, since each language variant of a form is an independent
     * record (see the Page Builder / Form Builder "duplicate to another
     * language" feature). `translation_group` links language variants of
     * "the same" form together — purely informational, no foreign key.
     */
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropUnique('forms_slug_unique');
            $table->unique(['slug', 'language']);
            $table->uuid('translation_group')->nullable()->after('language')->index();
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('translation_group');
            $table->dropUnique(['slug', 'language']);
            $table->unique('slug');
        });
    }
};
