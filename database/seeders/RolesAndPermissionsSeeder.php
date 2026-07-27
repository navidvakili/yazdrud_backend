<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Define all permissions and roles for the CMS.
     *
     * Permission naming convention: {module}.{action}
     * Actions: view, create, edit, delete, approve
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        /*
        |--------------------------------------------------------------------------
        | Permissions
        |--------------------------------------------------------------------------
        | Each module gets view/create/edit/delete/approve permissions.
        */
        $modules = [
            'dashboard'    => 'داشبورد',
            'users'        => 'مدیریت کاربران',
            'roles'        => 'مدیریت نقش‌ها',
            'navigation'   => 'مدیریت منو و دسترسی',
            'library'      => 'کتابخانه',
            'news'         => 'اخبار',
            'services'     => 'خدمات',
            'urban'        => ' شهری و عمرانی',
            'roads'        => 'roads transportation',
            'land'         => 'land allocation',
            'sessions'     => 'مدیریت نشست‌ها',
            'settings'     => 'تنظیمات سیستم',
        ];

        $actions = ['view', 'create', 'edit', 'delete', 'approve'];

        foreach ($modules as $module => $label) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$module}.{$action}",
                    'guard_name' => 'api',
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Roles (for both web and api guards)
        |--------------------------------------------------------------------------
        */
        $guards = ['web', 'api'];

        foreach ($guards as $guard) {
            // Admin — full access to everything
            $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guard]);
            $admin->givePermissionTo(Permission::where('guard_name', $guard)->get());

            // Editor — can view/edit/approve content but not manage users or settings
            $editor = Role::firstOrCreate(['name' => 'editor', 'guard_name' => $guard]);
            $editorPermissions = Permission::where('guard_name', $guard)->whereIn('name', [
                'dashboard.view',
                'library.view', 'library.create', 'library.edit',
                'news.view', 'news.create', 'news.edit', 'news.approve',
                'services.view', 'services.create', 'services.edit',
                'urban.view', 'urban.create', 'urban.edit',
                'roads.view', 'roads.create', 'roads.edit',
                'land.view', 'land.create', 'land.edit',
            ])->get();
            $editor->givePermissionTo($editorPermissions);

            // User — view-only access to most modules
            $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => $guard]);
            $userPermissions = Permission::where('guard_name', $guard)->whereIn('name', [
                'dashboard.view',
                'library.view',
                'news.view',
                'services.view',
                'urban.view',
                'roads.view',
                'land.view',
            ])->get();
            $userRole->givePermissionTo($userPermissions);
        }

        $this->command->info('✅ Roles and permissions seeded successfully.');
        $this->command->info('   Roles: admin, editor, user');
        $this->command->info('   Permissions: ' . Permission::count() . ' permissions across ' . count($modules) . ' modules');
    }
}
