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
        Schema::table('term_coupons', function (Blueprint $table) {
            $table->boolean('enable_installment')->default(false)->after('max_discount');
            $table->unsignedBigInteger('prepayment_amount')->nullable()->after('enable_installment');
            $table->enum('payment_method', ['online', 'offline'])->nullable()->after('prepayment_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('term_coupons', function (Blueprint $table) {
            $table->dropColumn(['enable_installment', 'prepayment_amount', 'payment_method']);
        });
    }
};
