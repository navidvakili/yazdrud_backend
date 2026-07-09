<?php

namespace App\Console\Commands;

use App\Models\RegistrationInstallment;
use App\Services\SmsService;
use Hekmatinasser\Verta\Verta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendInstallmentReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'installments:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send SMS reminders for overdue/pending installments whose due date has arrived';

    /**
     * Execute the console command.
     */
    public function handle(SmsService $smsService): void
    {
        $todayJalali = Verta::now()->format('Y/m/d');

        $this->info('Checking pending installments with due_date <= ' . $todayJalali);

        $dueInstallments = RegistrationInstallment::with('registration')
            ->where('status', 'pending')
            ->where('due_date', '<=', $todayJalali)
            ->whereNull('reminder_sent_at')
            ->get();

        if ($dueInstallments->isEmpty()) {
            $this->info('No due installments found.');
            return;
        }

        $sent = 0;
        $failed = 0;

        foreach ($dueInstallments as $installment) {
            $register = $installment->registration;

            if (!$register || !$register->mobile) {
                $this->warn("Installment #{$installment->id}: registration or mobile not found, skipping.");
                continue;
            }

            try {
                $smsService->sendByPattern(
                    'nzn5zwuedd0kaye',
                    ['faragir' => $register->enrollment_code ?? ''],
                    $register->mobile,
                );

                $installment->update(['reminder_sent_at' => now()]);

                $this->info("Reminder sent for installment #{$installment->id} to {$register->mobile}");
                $sent++;
            } catch (\Throwable $e) {
                $this->error("Failed to send reminder for installment #{$installment->id}: {$e->getMessage()}");
                Log::error('Installment reminder command failed', [
                    'installment_id' => $installment->id,
                    'error'          => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        $this->info("Done. Sent: {$sent}, Failed: {$failed}");
    }
}
