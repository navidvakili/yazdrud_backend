<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks the last time a token was actually used (throttled, updated by
     * EnsureTokenNotIdle) — the single source of truth for idle-timeout,
     * independent of whatever Passport itself does with `updated_at`.
     */
    public function up(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->timestamp('last_used_at')->nullable()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->dropColumn('last_used_at');
        });
    }
};
