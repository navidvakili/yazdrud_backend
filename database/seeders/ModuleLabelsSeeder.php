<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleLabelsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $labels = [
            ['module' => 'news',       'label' => 'اخبار'],
            ['module' => 'roles',      'label' => 'نقش‌ها'],
            ['module' => 'sessions',   'label' => 'نشست‌ها'],
            ['module' => 'users',      'label' => 'کاربران'],
        ];

        // Upsert: update label if module exists, insert if not
        foreach ($labels as $entry) {
            DB::table('module_labels')->updateOrInsert(
                ['module' => $entry['module']],
                ['label' => $entry['label'], 'updated_at' => now()]
            );
        }
    }
}
