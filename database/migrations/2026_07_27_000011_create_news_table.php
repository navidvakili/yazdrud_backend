<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->string('title', 300);
            $table->text('summary')->nullable();
            $table->longText('content');
            $table->string('category_id', 50)->nullable();
            $table->string('author_username', 191);
            $table->string('author_name', 200)->nullable();
            $table->string('author_role', 100)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('likes_count')->default(0);
            $table->boolean('is_pinned')->default(false);
            $table->enum('status', ['published', 'draft', 'archived'])->default('published');
            $table->enum('target_audience', ['all', 'students', 'professors', 'staff'])->default('all');
            $table->json('tags')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('news_categories')
                  ->onDelete('set null')->onUpdate('cascade');
            $table->foreign('author_username')->references('username')->on('users')
                  ->onDelete('cascade')->onUpdate('cascade');

            $table->index('status');
            $table->index('is_pinned');
            $table->index('target_audience');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news');
    }
};
