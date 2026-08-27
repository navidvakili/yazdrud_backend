<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_pages', function (Blueprint $table) {
            $table->id();
            $table->string('language', 10)->default('fa')->index();
            $table->string('title', 300);
            $table->string('slug', 191)->unique();
            $table->enum('status', ['published', 'draft'])->default('draft')->index();
            // SEO metadata (JSON: {title, description, keywords, og_image})
            $table->longText('seo')->nullable();
            // Full SmartPageSchema JSON (sections, globalStyles, version, ...)
            $table->longText('schema');
            $table->string('author_username', 191);
            $table->string('author_name', 200)->nullable();
            $table->string('author_role', 100)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreign('author_username')->references('username')->on('users')
                  ->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_pages');
    }
};
