<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('module_labels', function (Blueprint $table) {
            $table->string('module', 100)->primary();
            $table->string('label', 100);
            $table->timestamps();
        });

        // Seed with current module Persian labels
        $labels = [
            ['module' => 'news',       'label' => 'اخبار'],
            ['module' => 'roles',      'label' => 'نقش‌ها'],
            ['module' => 'sessions',   'label' => 'نشست‌ها'],
            ['module' => 'users',      'label' => 'کاربران'],
        ];

        DB::table('module_labels')->insert($labels);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_labels');
    }
};
