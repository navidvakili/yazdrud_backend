<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Change user_id from BIGINT to VARCHAR to support string primary keys (username).
     */
    public function up(): void
    {
        // oauth_access_tokens
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->string('user_id', 255)->nullable()->change();
        });

        // oauth_auth_codes
        Schema::table('oauth_auth_codes', function (Blueprint $table) {
            $table->string('user_id', 255)->change();
        });

        // oauth_device_codes
        Schema::table('oauth_device_codes', function (Blueprint $table) {
            $table->string('user_id', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });

        Schema::table('oauth_auth_codes', function (Blueprint $table) {
            $table->foreignId('user_id')->change();
        });

        Schema::table('oauth_device_codes', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });
    }
};
