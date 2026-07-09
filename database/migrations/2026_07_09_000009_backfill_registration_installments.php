<?php

use App\Models\Registertut;
use App\Models\RegistrationInstallment;
use App\Models\TermCoupon;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Create RegistrationInstallment records for existing registrations
     * that use an installment coupon but don't have any registration_installments yet.
     */
    public function up(): void
    {
        $registrations = Registertut::with('coupon.installmentItems')
            ->whereNotNull('coupon_id')
            ->where('refunded', false)
            ->get();

        $count = 0;

        foreach ($registrations as $reg) {
            // Skip if registration already has installment records
            if ($reg->installments()->count() > 0) {
                continue;
            }

            $coupon = $reg->coupon;
            if (!$coupon || !$coupon->enable_installment) {
                continue;
            }

            $items = $coupon->installmentItems()->orderBy('sort_order')->get();
            if ($items->isEmpty()) {
                continue;
            }

            foreach ($items as $item) {
                RegistrationInstallment::create([
                    'register_id'                 => $reg->id,
                    'voucher_installment_item_id'  => $item->id,
                    'title'                       => $item->title,
                    'amount'                      => (int) $item->amount,
                    'due_date'                    => $item->due_date,
                    'status'                      => 'pending',
                ]);
            }

            $count++;
        }

        echo "Backfilled {$count} registrations with installment records.\n";
    }

    /**
     * Reverse the migrations.
     *
     * Remove backfilled records (only those with no gateway_transaction_id).
     */
    public function down(): void
    {
        RegistrationInstallment::whereNull('gateway_transaction_id')
            ->whereNull('paid_at')
            ->delete();
    }
};
