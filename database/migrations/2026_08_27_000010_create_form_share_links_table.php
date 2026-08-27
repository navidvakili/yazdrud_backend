<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_share_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id')->unique();
            $table->string('slug', 191)->unique();
            $table->string('password')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->foreign('form_id')->references('id')->on('forms')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_share_links');
    }
};
