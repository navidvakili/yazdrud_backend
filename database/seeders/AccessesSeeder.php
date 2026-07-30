<?php

namespace Database\Seeders;

use App\Models\Access;
use Illuminate\Database\Seeder;

/**
 * Seed navigation menu items (accesses table).
 *
 * Roles format: pipe-delimited string e.g. "|admin|support|"
 * Parent = null or 0 for top-level categories
 * Parent = category id for children
 */
class AccessesSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing navigation items
        Access::query()->truncate();

        // ── 1. مدیریت سامانه (top-level) ───────────────────────────
        $adminCat = Access::create([
            'parent'   => null,
            'title'    => 'مدیریت سامانه',
            'url'      => '#',
            'icon'     => 'fa fa-cog',
            'roles'    => ['admin', 'support'],
            'ordering' => 1,
            'active'   => true,
        ]);

        Access::create([
            'parent'   => $adminCat->id,
            'title'    => 'مدیریت کاربران',
            'url'      => '/users',
            'icon'     => 'fa fa-users',
            'roles'    => ['admin', 'support'],
            'ordering' => 1,
            'active'   => true,
        ]);

        Access::create([
            'parent'   => $adminCat->id,
            'title'    => 'مدیریت نشست‌ها',
            'url'      => '/sessions',
            'icon'     => 'fa fa-clock',
            'roles'    => ['admin', 'support'],
            'ordering' => 2,
            'active'   => true,
        ]);

        // ── 2. اخبار و محتوا (top-level) ───────────────────────────
        $newsCat = Access::create([
            'parent'   => null,
            'title'    => 'اخبار و محتوا',
            'url'      => '#',
            'icon'     => 'fa fa-newspaper',
            'roles'    => ['admin', 'editor', 'support'],
            'ordering' => 2,
            'active'   => true,
        ]);

        Access::create([
            'parent'   => $newsCat->id,
            'title'    => 'لیست اخبار',
            'url'      => '/news',
            'icon'     => 'fa fa-file-text',
            'roles'    => ['admin', 'editor', 'support'],
            'ordering' => 1,
            'active'   => true,
        ]);

        Access::create([
            'parent'   => $newsCat->id,
            'title'    => 'دسته‌بندی اخبار',
            'url'      => '/news/categories',
            'icon'     => 'fa fa-folder',
            'roles'    => ['admin', 'editor', 'support'],
            'ordering' => 2,
            'active'   => true,
        ]);

        // ── 3. اسلایدر هوشمند (top-level) ──────────────────────────
        Access::create([
            'parent'   => null,
            'title'    => 'اسلایدر هوشمند',
            'url'      => '/slider-studio',
            'icon'     => 'fa fa-layers',
            'roles'    => ['admin', 'support'],
            'ordering' => 4,
            'active'   => true,
        ]);

        // ── 5. نقشه پروژه‌های عمرانی (top-level) ────────────────────
        $countyProjectsCat = Access::create([
            'parent'   => null,
            'title'    => 'نقشه پروژه‌های عمرانی',
            'url'      => '#',
            'icon'     => 'fa fa-map',
            'roles'    => ['admin', 'support'],
            'ordering' => 5,
            'active'   => true,
        ]);

        Access::create([
            'parent'   => $countyProjectsCat->id,
            'title'    => 'مدیریت شهرستان‌ها',
            'url'      => '/county-projects',
            'icon'     => 'fa fa-layers',
            'roles'    => ['admin', 'support'],
            'ordering' => 1,
            'active'   => true,
        ]);

    }
}
