<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add gateway_transaction_id and reminder_sent_at columns to
     * registration_installments for the online installment payment flow.
     */
    public function up(): void
    {
        Schema::table('registration_installments', function (Blueprint $table) {
            $table->foreignId('gateway_transaction_id')
                ->nullable()
                ->after('verified_by')
                ->constrained('gateway_transactions')
                ->nullOnDelete();
            $table->timestamp('reminder_sent_at')
                ->nullable()
                ->after('gateway_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registration_installments', function (Blueprint $table) {
            $table->dropForeign(['gateway_transaction_id']);
            $table->dropColumn('gateway_transaction_id');
            $table->dropColumn('reminder_sent_at');
        });
    }
};
