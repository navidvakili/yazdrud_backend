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
        Schema::table('registertuts', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->constrained('term_coupons')->nullOnDelete()->after('enrollment_code');
            $table->unsignedBigInteger('discount_amount')->nullable()->after('coupon_id');
            $table->unsignedBigInteger('prepayment_amount')->nullable()->after('discount_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registertuts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['discount_amount', 'prepayment_amount']);
        });
    }
};
