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
        Schema::create('registration_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('register_id')->constrained('registertuts')->cascadeOnDelete();
            $table->foreignId('voucher_installment_item_id')->nullable()->constrained('voucher_installment_items')->nullOnDelete();
            $table->string('title', 191);
            $table->unsignedBigInteger('amount');
            $table->string('due_date', 191)->comment('Jalali date');
            $table->enum('payment_method', ['online', 'offline'])->default('online');
            $table->enum('status', ['pending', 'paid', 'overdue'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('paid_amount')->nullable();
            $table->string('tracking_number', 191)->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign key for verified_by (references users table)
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_installments');
    }
};
