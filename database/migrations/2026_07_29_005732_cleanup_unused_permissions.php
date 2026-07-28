<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove unused permission modules (services, urban, roads, land) that
     * have no corresponding admin panel implementation in the frontend.
     *
     * These were originally seeded in RolesAndPermissionsSeeder but no admin
     * apps exist to manage them. The public-facing pages for these modules
     * are served by the separate "public" workspace, not the admin panel.
     */
    public function up(): void
    {
        $modules = ['services', 'urban', 'roads', 'land'];

        foreach ($modules as $module) {
            // Delete permissions (cascade will handle role_has_permissions)
            DB::table('permissions')
                ->where('name', 'like', "{$module}.%")
                ->where('guard_name', 'api')
                ->delete();
        }

        // Also clear Spatie cache so stale data isn't served
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse — re-create the removed permissions.
     */
    public function down(): void
    {
        $modules = ['services', 'urban', 'roads', 'land'];
        $actions = ['view', 'create', 'edit', 'delete', 'approve'];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                DB::table('permissions')->insert([
                    'name' => "{$module}.{$action}",
                    'guard_name' => 'api',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
