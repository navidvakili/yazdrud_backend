<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->string('title', 300);
            $table->string('slug', 191)->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['form', 'survey', 'quiz', 'registration'])->default('form')->index();
            $table->enum('status', ['draft', 'published', 'paused', 'archived'])->default('draft')->index();
            $table->string('category', 150)->nullable();
            $table->string('owner_username', 191)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('published_at')->nullable();
            // بلوک‌های JSON — دقیقاً مشابه الگوی smart_pages.schema (اعتبارسنجی فقط سطحی، بدون اسکیمای تودرتو)
            $table->longText('tags')->nullable();
            $table->longText('steps')->nullable();
            $table->longText('fields')->nullable();
            $table->longText('logic_rules')->nullable();
            $table->longText('quiz_config')->nullable();
            $table->longText('theme')->nullable();
            $table->longText('settings')->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('submissions_count')->default(0);
            $table->unsignedInteger('avg_completion_time_seconds')->nullable();
            $table->timestamps();

            $table->foreign('owner_username')->references('username')->on('users')
                  ->onDelete('set null')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
};
