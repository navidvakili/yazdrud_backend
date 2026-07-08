<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Change date columns from `date` to `string` (varchar) so that
     * Shamsi (Jalali) date strings can be stored without MySQL
     * Gregorian validation errors (e.g. 1405-04-31 is valid in
     * Persian calendar but invalid as Gregorian).
     */
    public function up(): void
    {
        $columns = [
            'start_date',
            'end_date',
            'registration_start_date',
            'registration_end_date',
        ];

        foreach ($columns as $column) {
            DB::statement("ALTER TABLE courses MODIFY `{$column}` VARCHAR(10) DEFAULT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columns = [
            'start_date',
            'end_date',
            'registration_start_date',
            'registration_end_date',
        ];

        foreach ($columns as $column) {
            DB::statement("ALTER TABLE courses MODIFY `{$column}` DATE DEFAULT NULL");
        }
    }
};
