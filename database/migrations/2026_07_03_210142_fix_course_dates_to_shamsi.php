<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Convert any Gregorian dates (year 202x) stored in courses date columns
     * back to Shamsi (Jalali) format. This fixes records that were created
     * before the CourseController was corrected to store raw Shamsi strings.
     *
     * Affected columns: start_date, end_date, registration_start_date, registration_end_date
     */
    public function up(): void
    {
        $dateColumns = [
            'start_date',
            'end_date',
            'registration_start_date',
            'registration_end_date',
        ];

        // Find all courses that have at least one Gregorian date
        $courses = DB::table('courses')
            ->where(function ($q) {
                foreach (['start_date','end_date','registration_start_date','registration_end_date'] as $col) {
                    $q->orWhere($col, 'like', '202%');
                }
            })
            ->get();

        $updated = 0;
        foreach ($courses as $course) {
            $needsUpdate = false;
            $updateData = [];

            foreach ($dateColumns as $col) {
                $rawValue = $course->$col;
                if ($rawValue && preg_match('/^202\d-\d{2}-\d{2}$/', $rawValue)) {
                    try {
                        $v = new \Hekmatinasser\Verta\Verta($rawValue);
                        $shamsi = $v->format('Y-m-d');
                        $updateData[$col] = $shamsi;
                        $needsUpdate = true;
                    } catch (\Exception $e) {
                        // Skip if conversion fails
                    }
                }
            }

            if ($needsUpdate) {
                DB::table('courses')
                    ->where('id', $course->id)
                    ->update($updateData);
                $updated++;
            }
        }

        echo "Converted {$updated} course(s) from Gregorian to Shamsi dates.\n";
    }

    /**
     * Reverse the migrations.
     *
     * There is no reliable way to reverse Gregorian→Shamsi conversion
     * without knowing the original values. This migration is one-way.
     */
    public function down(): void
    {
        // Intentional no-op: cannot reverse date conversion
    }
};
