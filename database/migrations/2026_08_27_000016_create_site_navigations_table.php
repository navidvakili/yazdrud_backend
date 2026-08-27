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
        Schema::create('site_navigations', function (Blueprint $table) {
            $table->id();
            // Language comes from the main multilingual system (languages table)
            $table->string('language', 10)->default('fa')->index();
            // Menu location in the site theme (e.g. Header Main Menu, Footer Menu 1, Mobile Menu)
            $table->string('location', 100)->index();
            // Unique slug per language + location (e.g. header-main-menu)
            $table->string('slug', 191);
            $table->string('name', 255)->comment('عنوان منو');
            // Full navigation tree JSON (NavigationItem[])
            $table->longText('items')->comment('درخت کامل ناوبری (JSON)');
            $table->enum('status', ['active', 'draft', 'archived'])->default('draft')->index();
            $table->unsignedInteger('version')->default(1)->comment('نسخه منتشرشده منو');
            $table->string('created_by', 191)->nullable()->comment('نام ایجادکننده');
            $table->timestamps();

            $table->unique(['language', 'location']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_navigations');
    }
};
