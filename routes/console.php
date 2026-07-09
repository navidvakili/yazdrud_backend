<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Installment Reminder Schedule ──
// php artisan installments:send-reminders
// To activate auto-reminders, add this line to your server's crontab:
// * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
// Then in App\Console\Kernel or routes/console.php schedule method:
// Schedule::command('installments:send-reminders')->dailyAt('08:00');
