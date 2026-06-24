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
        Schema::create('user_pinned_menus', function (Blueprint $table) {
            $table->id();
            $table->string('username', 191);
            $table->string('menu_id', 50);
            $table->timestamps();

            // Composite index for faster lookups (without unique constraint due to MySQL key length limits)
            $table->index(['username', 'menu_id'], 'user_pinned_username_menu_index');

            // Foreign key to users table
            $table->foreign('username')
                  ->references('username')
                  ->on('users')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_pinned_menus');
    }
};
