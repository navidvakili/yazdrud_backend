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
            if (!Schema::hasColumn('registertuts', 'certificate_approved')) {
                $table->boolean('certificate_approved')->default(false)->after('note')
                      ->comment('تایید شده برای صدور گواهی توسط مدیر');
            }
            if (!Schema::hasColumn('registertuts', 'certificate_approved_at')) {
                $table->timestamp('certificate_approved_at')->nullable()->after('certificate_approved')
                      ->comment('تاریخ تایید برای صدور گواهی');
            }
            if (!Schema::hasColumn('registertuts', 'certificate_approved_by')) {
                $table->string('certificate_approved_by', 50)->nullable()->after('certificate_approved_at')
                      ->comment('نام کاربری تایید کننده');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registertuts', function (Blueprint $table) {
            $table->dropColumn([
                'certificate_approved',
                'certificate_approved_at',
                'certificate_approved_by',
            ]);
        });
    }
};
