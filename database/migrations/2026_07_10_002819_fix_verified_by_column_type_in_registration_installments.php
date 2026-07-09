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
     * Note: The original migration attempted to add a foreign key to users.id (which doesn't exist),
     * so no valid FK constraint exists on this column that needs to be dropped.
     */
    public function up(): void
    {
        // Change column from bigint unsigned to varchar(191) to match users.username PK type
        DB::statement('ALTER TABLE registration_installments MODIFY verified_by VARCHAR(191) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE registration_installments MODIFY verified_by BIGINT UNSIGNED NULL');
    }
};
