<?php

namespace Database\Seeders;

use App\Models\CountyProject;
use Illuminate\Database\Seeder;

class CountyProjectSeeder extends Seeder
{
    /**
     * Seed the county_projects table with initial data for all 12 counties.
     */
    public function run(): void
    {
        $counties = [
            [
                'county_id' => 'yazd',
                'county_name' => 'یزد',
                'road_projects_count' => 45,
                'housing_units_count' => 4820,
                'urban_plans_count' => 12,
                'road_progress' => 82,
                'housing_progress' => 65,
                'urban_progress' => 70,
                'has_active_road_project' => true,
                'has_housing_workshop' => true,
                'description' => 'مرکز استان و کانون توسعه با بزرگترین پروژه‌های انبوه‌سازی مسکن و تقاطع‌های غیرهمسطح.',
            ],
            [
                'county_id' => 'meybod',
                'county_name' => 'میبد',
                'road_projects_count' => 22,
                'housing_units_count' => 1430,
                'urban_plans_count' => 6,
                'road_progress' => 75,
                'housing_progress' => 58,
                'urban_progress' => 65,
                'has_active_road_project' => true,
                'has_housing_workshop' => false,
                'description' => 'دومین شهرستان پرجمعیت با تمرکز بر بازآفرینی شهری بافت تاریخی و بهبود دسترسی‌های جاده‌ای صنایع.',
            ],
            [
                'county_id' => 'ardakan',
                'county_name' => 'اردکان',
                'road_projects_count' => 35,
                'housing_units_count' => 2550,
                'urban_plans_count' => 8,
                'road_progress' => 78,
                'housing_progress' => 55,
                'urban_progress' => 60,
                'has_active_road_project' => true,
                'has_housing_workshop' => true,
                'description' => 'پهناورترین شهرستان با احداث باندهای بزرگراهی متصل به خطوط ترانزیت ملی و مسکن کارگری صنایع.',
            ],
            [
                'county_id' => 'bafq',
                'county_name' => 'بافق',
                'road_projects_count' => 18,
                'housing_units_count' => 910,
                'urban_plans_count' => 4,
                'road_progress' => 70,
                'housing_progress' => 45,
                'urban_progress' => 50,
                'has_active_road_project' => true,
                'has_housing_workshop' => false,
                'description' => 'قطب معدنی استان با تمرکز بر توسعه بزرگراه‌های متصل به معادن سنگ آهن و ریلی مسافربری.',
            ],
            [
                'county_id' => 'mehriz',
                'county_name' => 'مهریز',
                'road_projects_count' => 15,
                'housing_units_count' => 1180,
                'urban_plans_count' => 5,
                'road_progress' => 68,
                'housing_progress' => 60,
                'urban_progress' => 55,
                'has_active_road_project' => false,
                'has_housing_workshop' => true,
                'description' => 'دروازه جنوبی استان با احداث کمربندی جدید و ساخت خانه‌های ویلایی یک طبقه در طرح مسکن ملی.',
            ],
            [
                'county_id' => 'taft',
                'county_name' => 'تفت',
                'road_projects_count' => 12,
                'housing_units_count' => 790,
                'urban_plans_count' => 4,
                'road_progress' => 60,
                'housing_progress' => 40,
                'urban_progress' => 45,
                'has_active_road_project' => false,
                'has_housing_workshop' => false,
                'description' => 'منطقه پایکوهی یزد با طرح‌های بهسازی معابر روستایی و حفاظت از پهنه‌های باغ‌شهری و تفرجگاهی.',
            ],
            [
                'county_id' => 'abarkuh',
                'county_name' => 'ابرکوه',
                'road_projects_count' => 14,
                'housing_units_count' => 650,
                'urban_plans_count' => 3,
                'road_progress' => 55,
                'housing_progress' => 35,
                'urban_progress' => 40,
                'has_active_road_project' => false,
                'has_housing_workshop' => false,
                'description' => 'کهن‌شهر غربی استان با محور مواصلاتی یزد-شیراز و توسعه خدمات شهری بر محور سرو کهنسال.',
            ],
            [
                'county_id' => 'ashkezar',
                'county_name' => 'اشکذر',
                'road_projects_count' => 8,
                'housing_units_count' => 520,
                'urban_plans_count' => 3,
                'road_progress' => 50,
                'housing_progress' => 30,
                'urban_progress' => 35,
                'has_active_road_project' => false,
                'has_housing_workshop' => false,
                'description' => 'مرکز پرورش اسب و کانون گلخانه‌ای با طرح‌های بهبود راه‌های روستایی و حریم شهری.',
            ],
            [
                'county_id' => 'behabad',
                'county_name' => 'بهاباد',
                'road_projects_count' => 10,
                'housing_units_count' => 495,
                'urban_plans_count' => 2,
                'road_progress' => 45,
                'housing_progress' => 25,
                'urban_progress' => 30,
                'has_active_road_project' => false,
                'has_housing_workshop' => false,
                'description' => 'دورافتاده‌ترین منطقه توسعه با احداث راه‌های امن کویری و واگذاری اراضی به ساکنین بومی.',
            ],
            [
                'county_id' => 'khatam',
                'county_name' => 'خاتم',
                'road_projects_count' => 11,
                'housing_units_count' => 410,
                'urban_plans_count' => 2,
                'road_progress' => 52,
                'housing_progress' => 28,
                'urban_progress' => 32,
                'has_active_road_project' => false,
                'has_housing_workshop' => false,
                'description' => 'قطب کشاورزی با تمرکز بر آسفالت راه‌های بین‌مزارع و بهسازی ورودی شهر هرات.',
            ],
            [
                'county_id' => 'zarch',
                'county_name' => 'زارچ',
                'road_projects_count' => 6,
                'housing_units_count' => 380,
                'urban_plans_count' => 2,
                'road_progress' => 40,
                'housing_progress' => 50,
                'urban_progress' => 35,
                'has_active_road_project' => false,
                'has_housing_workshop' => true,
                'description' => 'شهرستان شرق یزد با تمرکز بر توسعه راه‌های روستایی و طرح‌های مسکن ملی حومه‌ای.',
            ],
            [
                'county_id' => 'marvast',
                'county_name' => 'مروست',
                'road_projects_count' => 5,
                'housing_units_count' => 290,
                'urban_plans_count' => 1,
                'road_progress' => 35,
                'housing_progress' => 20,
                'urban_progress' => 25,
                'has_active_road_project' => false,
                'has_housing_workshop' => false,
                'description' => 'منطقه دورافتاده جنوب استان با محور مواصلاتی خاتم-مروست و طرح‌های بهسازی راه‌های کوهستانی.',
            ],
        ];

        foreach ($counties as $county) {
            CountyProject::create($county);
        }

        $this->command->info('✅ County projects seeded successfully for all 12 counties.');
    }
}
