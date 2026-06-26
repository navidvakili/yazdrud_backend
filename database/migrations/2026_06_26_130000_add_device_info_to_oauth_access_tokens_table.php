<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('user_id');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->text('browser_fingerprint')->nullable()->after('user_agent');
        });
    }

    public function down(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent', 'browser_fingerprint']);
        });
    }
};
