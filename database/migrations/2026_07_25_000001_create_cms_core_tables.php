<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Users ───────────────────────────────────────────────────
        Schema::create('users', function (Blueprint $table) {
            $table->string('username', 191)->primary();
            $table->string('fname', 100)->nullable();
            $table->string('lname', 100)->nullable();
            $table->string('kodmeli', 20)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('password');
            $table->string('role', 50)->default('user');
            $table->string('sign')->nullable();
            $table->string('theme', 10)->default('light');
            $table->string('two_factor_secret')->nullable();
            $table->rememberToken();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        // ─── Accesses (navigation / menu items) ──────────────────────
        Schema::create('accesses', function (Blueprint $table) {
            $table->id();
            $table->string('parent', 50)->nullable();
            $table->string('title', 100);
            $table->string('url', 255)->nullable();
            $table->string('icon', 100)->nullable();
            $table->json('roles')->nullable();
            $table->integer('ordering')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // ─── User Pinned Menus ───────────────────────────────────────
        Schema::create('user_pinned_menus', function (Blueprint $table) {
            $table->id();
            $table->string('username', 191);
            $table->string('menu_id', 50);
            $table->timestamps();
            $table->index(['username', 'menu_id'], 'user_pinned_username_menu_index');
            $table->foreign('username')->references('username')->on('users')
                  ->onDelete('cascade')->onUpdate('cascade');
        });

        // ─── Session Warnings ────────────────────────────────────────
        Schema::create('session_warnings', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('browser_fingerprint')->nullable();
            $table->string('new_token_id')->nullable();
            $table->string('poll_token');
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->foreign('user_id')->references('username')->on('users')->onDelete('cascade');
            $table->index('user_id');
            $table->unique('poll_token');
        });

        // ─── Gateway Transactions ────────────────────────────────────
        Schema::create('gateway_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('username', 191);
            $table->string('type', 50)->nullable();
            $table->enum('status', ['pending', 'paid', 'failed', 'reversed'])->default('pending');
            $table->enum('port', [
                'MELLAT', 'SADAD', 'ZARINPAL', 'PAYLINE', 'JAHANPAY',
                'PARSIAN', 'PASARGAD', 'SAMAN', 'ASANPARDAKHT', 'PAYPAL',
                'PAYIR', 'IRANKISH', 'fish', 'FREE'
            ])->default('FREE');
            $table->bigInteger('price')->default(0);
            $table->string('ref_id')->nullable();
            $table->string('tracking_code')->nullable();
            $table->string('card_number')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('description')->nullable();
            $table->timestamp('payment_date')->nullable();
            $table->timestamps();
            $table->foreign('username')->references('username')->on('users')
                  ->onDelete('cascade')->onUpdate('cascade');
        });

        // ─── Cache ───────────────────────────────────────────────────
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });
        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });

        // ─── Jobs & Queue ───────────────────────────────────────────
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        // ─── Personal Access Tokens (Laravel Passport) ───────────────
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // ─── OAuth Tables (Laravel Passport) ─────────────────────────
        Schema::create('oauth_auth_codes', function (Blueprint $table) {
            $table->char('id', 80)->primary();
            $table->string('user_id', 191)->index();
            $table->foreignUuid('client_id');
            $table->text('scopes')->nullable();
            $table->boolean('revoked');
            $table->dateTime('expires_at')->nullable();
        });
        Schema::create('oauth_access_tokens', function (Blueprint $table) {
            $table->char('id', 80)->primary();
            $table->string('user_id', 191)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('browser_fingerprint')->nullable();
            $table->foreignUuid('client_id');
            $table->string('name')->nullable();
            $table->text('scopes')->nullable();
            $table->boolean('revoked');
            $table->timestamps();
            $table->dateTime('expires_at')->nullable();
        });
        Schema::create('oauth_refresh_tokens', function (Blueprint $table) {
            $table->char('id', 80)->primary();
            $table->char('access_token_id', 80)->index();
            $table->boolean('revoked');
            $table->dateTime('expires_at')->nullable();
        });
        Schema::create('oauth_clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->string('name');
            $table->string('secret')->nullable();
            $table->string('provider')->nullable();
            $table->text('redirect_uris');
            $table->text('grant_types');
            $table->boolean('revoked');
            $table->timestamps();
        });
        Schema::create('oauth_device_codes', function (Blueprint $table) {
            $table->char('id', 80)->primary();
            $table->string('user_id', 191)->nullable()->index();
            $table->foreignUuid('client_id')->index();
            $table->char('user_code', 8)->unique();
            $table->text('scopes');
            $table->boolean('revoked');
            $table->dateTime('user_approved_at')->nullable();
            $table->dateTime('last_polled_at')->nullable();
            $table->dateTime('expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_device_codes');
        Schema::dropIfExists('oauth_clients');
        Schema::dropIfExists('oauth_refresh_tokens');
        Schema::dropIfExists('oauth_access_tokens');
        Schema::dropIfExists('oauth_auth_codes');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('gateway_transactions');
        Schema::dropIfExists('session_warnings');
        Schema::dropIfExists('user_pinned_menus');
        Schema::dropIfExists('accesses');
        Schema::dropIfExists('users');
    }
};
