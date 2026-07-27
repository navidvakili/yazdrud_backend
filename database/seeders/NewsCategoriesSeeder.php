<?php

namespace Database\Seeders;

use App\Models\NewsCategory;
use Illuminate\Database\Seeder;

class NewsCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'آموزشی و انتخاب واحد',
                'slug' => 'educational',
                'color' => 'bg-teal-500/10 text-teal-700 dark:text-teal-300 border-teal-500/30',
                'description' => 'اطلاعیه‌های تقویم آموزشی، امتحانات، حذف و اضافه',
                'ordering' => 1,
            ],
            [
                'name' => 'پژوهشی و فناوری',
                'slug' => 'research',
                'color' => 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border-indigo-500/30',
                'description' => 'مقالات علمی، جشنواره‌ها، آزمایشگاه‌ها و مراکز رشد',
                'ordering' => 2,
            ],
            [
                'name' => 'مالی و صندوق رفاه',
                'slug' => 'finance',
                'color' => 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/30',
                'description' => 'شهریه، تسهیلات بانکی، تخفیف‌ها و اقساط',
                'ordering' => 3,
            ],
            [
                'name' => 'کارگاه‌ها و همایش‌ها',
                'slug' => 'workshops',
                'color' => 'bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-500/30',
                'description' => 'دوره‌های مهارتی، وبینارها و کنفرانس‌های علمی',
                'ordering' => 4,
            ],
            [
                'name' => 'فرهنگی و ورزشی',
                'slug' => 'cultural',
                'color' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
                'description' => 'مسابقات، اردوها، انجمن‌های علمی و کانون‌ها',
                'ordering' => 5,
            ],
            [
                'name' => 'روابط عمومی و امور عمومی',
                'slug' => 'general',
                'color' => 'bg-purple-500/10 text-purple-700 dark:text-purple-300 border-purple-500/30',
                'description' => 'اطلاعیه‌های کلی دانشگاه و افتخارات ملی',
                'ordering' => 6,
            ],
        ];

        foreach ($categories as $cat) {
            NewsCategory::updateOrCreate(
                ['slug' => $cat['slug']],
                $cat
            );
        }
    }
}
