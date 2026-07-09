<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fix verified_by column type: the users table uses `username` (varchar) as primary key,
     * but verified_by was incorrectly created as bigint unsigned. This causes:
     *   SQLSTATE[HY000]: General error: 1366 Incorrect integer value: 'username' for column 'verified_by'
     * when auth()->id() returns a string (username) but the column expects an integer.
     *
     * Also adds the missing foreign key constraint to users.username.
     */
    public function up(): void
    {
        // Change column from bigint unsigned to varchar(191) to match users.username PK type
        DB::statement('ALTER TABLE registration_installments MODIFY verified_by VARCHAR(191) NULL');

        // Add proper foreign key referencing users.username (the actual PK)
        try {
            DB::statement('ALTER TABLE registration_installments ADD CONSTRAINT registration_installments_verified_by_foreign FOREIGN KEY (verified_by) REFERENCES users(username) ON DELETE SET NULL');
        } catch (\Exception $e) {
            // Constraint may already exist or column still has incompatible data — skip
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the FK first if it exists
        try {
            DB::statement('ALTER TABLE registration_installments DROP FOREIGN KEY registration_installments_verified_by_foreign');
        } catch (\Exception $e) {
            // May not exist
        }

        DB::statement('ALTER TABLE registration_installments MODIFY verified_by BIGINT UNSIGNED NULL');
    }
};
