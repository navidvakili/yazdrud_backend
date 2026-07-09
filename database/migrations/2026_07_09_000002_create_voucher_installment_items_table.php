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
        Schema::create('voucher_installment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('term_coupon_id')->constrained('term_coupons')->cascadeOnDelete();
            $table->string('title', 191);
            $table->unsignedBigInteger('amount');
            $table->string('due_date', 191)->comment('Jalali date');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_installment_items');
    }
};
