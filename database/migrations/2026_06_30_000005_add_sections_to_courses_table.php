<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->json('sections')->nullable()->after('group_id')->comment('JSON array: normal, featured, pre_register, free');
        });

        // Migrate existing data: copy section value into sections as JSON array
        DB::statement("UPDATE courses SET sections = JSON_ARRAY(CASE WHEN section IS NULL OR section = '' THEN 'normal' ELSE section END) WHERE sections IS NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('sections');
        });
    }
};
