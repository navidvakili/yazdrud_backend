<?php

namespace Database\Seeders;

use App\Models\HeroSlide;
use Illuminate\Database\Seeder;

class HeroSlideSeeder extends Seeder
{
    /**
     * Seed the hero slides with initial data matching the public site.
     */
    public function run(): void
    {
        $slides = [
            [
                'tag'                   => 'پروژه پیشران مسکن',
                'title'                 => 'نهضت ملی مسکن و واگذاری اراضی یزد',
                'subtitle'              => 'واگذاری اراضی مسکونی و ساخت خانه‌های ویلایی و تک‌واحدی متناسب با زیست‌بوم و بادگیرهای اصیل یزد',
                'badge'                 => '۱۴۰ پروژه مسکونی فعال',
                'badge_icon'            => 'fa-house-chimney',
                'bg_image'              => null,
                'primary_cta_text'      => 'ورود به سامانه نهضت مسکن',
                'primary_cta_target'    => 'services',
                'secondary_cta_text'    => 'استعلام فوری وضعیت فرم ج',
                'secondary_cta_target'  => 'services',
                'sort_order'            => 1,
                'is_active'             => true,
            ],
            [
                'tag'                   => 'زیرساخت و ترانزیت',
                'title'                 => 'توسعه بزرگراه‌ها و راه‌های شریانی استان',
                'subtitle'              => 'بهسازی، دوبانده‌سازی و ارتقای ایمنی بیش از ۸۵۰ کیلومتر از محورهای اصلی و کویری استان یزد',
                'badge'                 => '۸۵۰ کیلومتر راه ترانزیتی',
                'badge_icon'            => 'fa-road',
                'bg_image'              => null,
                'primary_cta_text'      => 'نقشه پروژه‌های جاده‌ای یزد',
                'primary_cta_target'    => 'interactive-map',
                'secondary_cta_text'    => 'گزارش پروژه‌های راه‌سازی',
                'secondary_cta_target'  => 'news',
                'sort_order'            => 2,
                'is_active'             => true,
            ],
            [
                'tag'                   => 'شهرسازی و میراث جهانی',
                'title'                 => 'بازآفرینی شهری و احیای بافت تاریخی یزد',
                'subtitle'              => 'حفظ و احیای هویت خشتی ثبت شده در یونسکو، بهسازی بافت فرسوده و بازآفرینی محلات کهن استان',
                'badge'                 => '۳۲۰ پروژه عمران شهری',
                'badge_icon'            => 'fa-city',
                'bg_image'              => null,
                'primary_cta_text'      => 'طرح‌های بازآفرینی شهری',
                'primary_cta_target'    => 'services',
                'secondary_cta_text'    => 'مشاهده آخرین اخبار شهرسازی',
                'secondary_cta_target'  => 'news',
                'sort_order'            => 3,
                'is_active'             => true,
            ],
            [
                'tag'                   => 'حمایت از خانواده و جمعیت',
                'title'                 => 'طرح قانون حمایت از خانواده و جوانی جمعیت',
                'subtitle'              => 'تخصیص اراضی رایگان به خانوارهای دارای ۳ فرزند و بیشتر و جوانان متقاضی مسکن در کلیه شهرستان‌های یزد',
                'badge'                 => '۱۲۰ خدمت آنلاین پورتال',
                'badge_icon'            => 'fa-users',
                'bg_image'              => null,
                'primary_cta_text'      => 'ثبت‌نام طرح جوانی جمعیت',
                'primary_cta_target'    => 'services',
                'secondary_cta_text'    => 'میز خدمت هوشمند',
                'secondary_cta_target'  => 'services',
                'sort_order'            => 4,
                'is_active'             => true,
            ],
        ];

        foreach ($slides as $slide) {
            HeroSlide::create($slide);
        }

        $this->command->info('✅ Hero slides seeded: ' . count($slides) . ' slides');
    }
}
