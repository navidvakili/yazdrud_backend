<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add 'TEST_BYPASS' to the gateway_transactions.port ENUM so that
     * local/test mode registration bypasses can be recorded without error.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE `gateway_transactions` CHANGE `port` `port` ENUM('MELLAT','SADAD','ZARINPAL','PAYLINE','JAHANPAY','PARSIAN','PASARGAD','SAMAN','ASANPARDAKHT','PAYPAL','PAYIR','IRANKISH','fish','FREE','TEST_BYPASS') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `gateway_transactions` CHANGE `port` `port` ENUM('MELLAT','SADAD','ZARINPAL','PAYLINE','JAHANPAY','PARSIAN','PASARGAD','SAMAN','ASANPARDAKHT','PAYPAL','PAYIR','IRANKISH','fish','FREE') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
    }
};
