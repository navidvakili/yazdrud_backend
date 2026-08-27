<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laravel's standard password-reset token table — required by the
     * password broker (config/auth.php: passwords.users.table). It was
     * never created, which meant forgotPassword()/resetPassword() in
     * AuthController threw a "table not found" SQL error for every user.
     * Ported from sau, where the same pre-existing bug was discovered and
     * fixed while building the session-hardening feature.
     */
    public function up(): void
    {
        Schema::create('password_resets', function (Blueprint $table) {
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_resets');
    }
};
