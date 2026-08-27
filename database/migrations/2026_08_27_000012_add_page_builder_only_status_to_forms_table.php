<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE forms MODIFY status ENUM('draft', 'published', 'paused', 'archived', 'page_builder_only') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("UPDATE forms SET status = 'draft' WHERE status = 'page_builder_only'");
        DB::statement("ALTER TABLE forms MODIFY status ENUM('draft', 'published', 'paused', 'archived') NOT NULL DEFAULT 'draft'");
    }
};
