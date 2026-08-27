<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->onDelete('cascade');
            $table->string('tracking_code', 32)->unique();
            $table->string('respondent_name', 200)->nullable();
            $table->string('respondent_email', 191)->nullable();
            $table->string('respondent_role', 100)->nullable();
            $table->enum('status', ['new', 'under_review', 'approved', 'rejected'])->default('new')->index();
            $table->longText('answers');
            $table->unsignedInteger('score_total')->nullable();
            $table->string('grade_label', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedInteger('completion_time_seconds')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('expert_assigned', 191)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
