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
        Schema::create('session_warnings', function (Blueprint $table) {
            $table->id();
            $table->string('user_id'); // matches users.username (primary key)
            $table->string('new_token_id')->nullable(); // oauth_access_tokens.id, set when accepted
            $table->string('poll_token'); // secret token for new session to poll status
            $table->string('status')->default('pending'); // pending, accepted, rejected
            $table->timestamps();

            $table->foreign('user_id')->references('username')->on('users')->onDelete('cascade');
            $table->index('user_id');
            $table->unique('poll_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_warnings');
    }
};
