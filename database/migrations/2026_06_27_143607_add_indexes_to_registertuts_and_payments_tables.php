<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds missing indexes on registertuts, registertuts_payments, and
     * gateway_transactions tables to fix severe performance issues.
     * Original table-creation migrations are missing from the project,
     * so no indexes were ever defined on join/filter columns.
     *
     * Affected slow queries:
     *   - allRegistrations()  → whereHas('payment.transaction')
     *   - confirmedRegistrations() → whereHas('payment.transaction')
     *   - CourseStatisticsController::index()  → LEFT JOIN + WHERE BETWEEN
     *   - formatRegistration()  → lazy-load $reg->payment
     */
    public function up(): void
    {
        // ─── registertuts (main registration table) ───
        Schema::table('registertuts', function (Blueprint $table) {
            // FK / JOIN / filter columns
            $table->index('course_id', 'idx_registertuts_course_id');

            // Range filter in statistics (WHERE BETWEEN created_at)
            $table->index('created_at', 'idx_registertuts_created_at');

            // Boolean filters used in WHERE clauses
            $table->index('refunded', 'idx_registertuts_refunded');
            $table->index('verified_receipt', 'idx_registertuts_verified_receipt');

            // Payment method filter (online / bank)
            $table->index('payment_method', 'idx_registertuts_payment_method');

            // Search columns (LIKE queries in allRegistrations)
            $table->index('fullname', 'idx_registertuts_fullname');
            $table->index('kodmeli', 'idx_registertuts_kodmeli');
            $table->index('mobile', 'idx_registertuts_mobile');

            // Composite index for statistics queries:
            //   WHERE refunded=false AND created_at BETWEEN ...
            $table->index(['refunded', 'created_at'], 'idx_registertuts_refunded_created');
        });

        // ─── registertuts_payments (payment ↔ transaction pivot) ───
        Schema::table('registertuts_payments', function (Blueprint $table) {
            // JOIN column: registertuts_payments.register_id → registertuts.id
            // Used in whereHas('payment.transaction') subquery
            $table->index('register_id', 'idx_rpayments_register_id');

            // JOIN column: registertuts_payments.transaction_id → gateway_transactions.id
            $table->index('transaction_id', 'idx_rpayments_transaction_id');
        });

        // ─── gateway_transactions (bank gateway records) ───
        Schema::table('gateway_transactions', function (Blueprint $table) {
            // WHERE filter: status = 'SUCCEED' (used in subquery)
            $table->index('status', 'idx_gateway_tx_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registertuts', function (Blueprint $table) {
            $table->dropIndex('idx_registertuts_course_id');
            $table->dropIndex('idx_registertuts_created_at');
            $table->dropIndex('idx_registertuts_refunded');
            $table->dropIndex('idx_registertuts_verified_receipt');
            $table->dropIndex('idx_registertuts_payment_method');
            $table->dropIndex('idx_registertuts_fullname');
            $table->dropIndex('idx_registertuts_kodmeli');
            $table->dropIndex('idx_registertuts_mobile');
            $table->dropIndex('idx_registertuts_refunded_created');
        });

        Schema::table('registertuts_payments', function (Blueprint $table) {
            $table->dropIndex('idx_rpayments_register_id');
            $table->dropIndex('idx_rpayments_transaction_id');
        });

        Schema::table('gateway_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_gateway_tx_status');
        });
    }
};
