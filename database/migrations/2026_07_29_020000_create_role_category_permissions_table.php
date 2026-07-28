<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_category_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')
                ->constrained('spatie_roles')
                ->cascadeOnDelete();
            $table->string('category_type', 50);       // e.g. 'news'
            $table->unsignedBigInteger('category_id');  // FK to the category table (e.g. news_categories.id)
            $table->string('permission', 20);           // 'view', 'create', 'edit', 'delete'
            $table->timestamps();

            $table->unique(['role_id', 'category_type', 'category_id', 'permission'], 'rcp_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_category_permissions');
    }
};
