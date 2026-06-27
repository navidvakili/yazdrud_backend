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
            if (!Schema::hasColumn('registertuts', 'refunded')) {
                $table->boolean('refunded')->default(false)->after('rejection_reason')
                      ->comment('مستردد شده');
            }
            if (!Schema::hasColumn('registertuts', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable()->after('refunded')
                      ->comment('تاریخ مستردد');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registertuts', function (Blueprint $table) {
            $table->dropColumn(['refunded', 'refunded_at']);
        });
    }
};
