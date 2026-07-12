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
        Schema::table('registration_installments', function (Blueprint $table) {
            $table->string('receipt_image', 255)->nullable()->after('deposit_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registration_installments', function (Blueprint $table) {
            $table->dropColumn('receipt_image');
        });
    }
};
