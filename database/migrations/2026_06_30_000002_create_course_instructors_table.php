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
        Schema::create('course_instructors', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->string('name', 255)->comment('نام کامل استاد');
            $table->string('specialty', 255)->nullable()->comment('تخصص');
            $table->text('bio')->nullable()->comment('بیوگرافی');
            $table->string('photo', 255)->nullable()->comment('تصویر استاد');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_instructors');
    }
};
