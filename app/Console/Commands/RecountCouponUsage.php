<?php

namespace App\Console\Commands;

use App\Models\TermCoupon;
use Illuminate\Console\Command;

class RecountCouponUsage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coupons:recount-usage';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate used_count for all coupons based on actual registration records';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $coupons = TermCoupon::all();
        $bar = $this->output->createProgressBar($coupons->count());
        $bar->start();

        $updated = 0;
        foreach ($coupons as $coupon) {
            $actualCount = $coupon->registrations()->count();
            if ((int) $coupon->used_count !== $actualCount) {
                $coupon->timestamps = false; // Don't touch updated_at
                $coupon->used_count = $actualCount;
                $coupon->save();
                $updated++;
                $this->line(PHP_EOL . "  {$coupon->code}: {$coupon->used_count} → {$actualCount}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ {$updated} coupon(s) updated out of {$coupons->count()} total.");

        return Command::SUCCESS;
    }
}
