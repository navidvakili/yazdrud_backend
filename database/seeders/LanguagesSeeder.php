<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguagesSeeder extends Seeder
{
    /**
     * Seed the default languages (fa only — single-language project for now).
     */
    public function run(): void
    {
        $languages = [
            ['code' => 'fa', 'name' => 'فارسی', 'name_en' => 'Persian', 'dir' => 'rtl', 'is_active' => true, 'is_default' => true, 'ordering' => 1],
        ];

        foreach ($languages as $language) {
            Language::updateOrCreate(
                ['code' => $language['code']],
                $language
            );
        }
    }
}
