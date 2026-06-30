<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add 'FREE' to the gateway_transactions.port ENUM so that free
     * course registrations (price = 0) can be recorded without error.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE `gateway_transactions` CHANGE `port` `port` ENUM('MELLAT','SADAD','ZARINPAL','PAYLINE','JAHANPAY','PARSIAN','PASARGAD','SAMAN','ASANPARDAKHT','PAYPAL','PAYIR','IRANKISH','fish','FREE') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `gateway_transactions` CHANGE `port` `port` ENUM('MELLAT','SADAD','ZARINPAL','PAYLINE','JAHANPAY','PARSIAN','PASARGAD','SAMAN','ASANPARDAKHT','PAYPAL','PAYIR','IRANKISH','fish') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
    }
};
