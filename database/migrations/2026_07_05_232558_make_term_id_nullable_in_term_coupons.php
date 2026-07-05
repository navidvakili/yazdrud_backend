<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop foreign key, then make nullable (raw SQL — no doctrine/dbal needed)
        DB::statement('ALTER TABLE `term_coupons` DROP FOREIGN KEY `term_coupons_term_id_foreign`');
        DB::statement('ALTER TABLE `term_coupons` MODIFY `term_id` BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE `term_coupons` ADD CONSTRAINT `term_coupons_term_id_foreign` FOREIGN KEY (`term_id`) REFERENCES `terms`(`id`) ON DELETE SET NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE `term_coupons` DROP FOREIGN KEY `term_coupons_term_id_foreign`');
        DB::statement('ALTER TABLE `term_coupons` MODIFY `term_id` BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE `term_coupons` ADD CONSTRAINT `term_coupons_term_id_foreign` FOREIGN KEY (`term_id`) REFERENCES `terms`(`id`)');
    }
};
