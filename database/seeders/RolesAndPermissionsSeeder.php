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
            'users'           => 'مدیریت کاربران',
            'roles'           => 'مدیریت نقش‌ها',
            'news'            => 'اخبار',
            'sessions'        => 'مدیریت نشست‌ها',
            'county-projects' => 'نقشه پروژه‌های عمرانی',
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
            // Support (پشتیبان) — full access to everything + role switching
            $support = Role::firstOrCreate(['name' => 'support', 'guard_name' => $guard]);
            $support->givePermissionTo(Permission::where('guard_name', $guard)->get());

            // Admin — limited to users, sessions, news management
            $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guard]);
            $adminPermissions = Permission::where('guard_name', $guard)->whereIn('name', [
                'users.view', 'users.create', 'users.edit', 'users.delete',
                'sessions.view', 'sessions.create', 'sessions.edit', 'sessions.delete',
                'news.view', 'news.create', 'news.edit', 'news.delete', 'news.approve',
                'county-projects.view', 'county-projects.edit',
            ])->get();
            $admin->givePermissionTo($adminPermissions);

            // Editor — can view/edit/approve content but not manage users
            $editor = Role::firstOrCreate(['name' => 'editor', 'guard_name' => $guard]);
            $editorPermissions = Permission::where('guard_name', $guard)->whereIn('name', [
                'news.view', 'news.create', 'news.edit', 'news.approve',
            ])->get();
            $editor->givePermissionTo($editorPermissions);

            // User — view-only access to most modules
            $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => $guard]);
            $userPermissions = Permission::where('guard_name', $guard)->whereIn('name', [
                'news.view',
            ])->get();
            $userRole->givePermissionTo($userPermissions);
        }

        $this->command->info('✅ Roles and permissions seeded successfully.');
        $this->command->info('   Roles: support (developer), admin, editor, user');
        $this->command->info('   Permissions: ' . Permission::count() . ' permissions across ' . count($modules) . ' modules');
    }
}
