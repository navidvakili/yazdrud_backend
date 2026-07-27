<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove dashboard permissions from role_has_permissions
        $dashboardPermIds = DB::table('permissions')
            ->where('name', 'like', 'dashboard.%')
            ->pluck('id');

        if ($dashboardPermIds->isNotEmpty()) {
            DB::table('role_has_permissions')
                ->whereIn('permission_id', $dashboardPermIds)
                ->delete();
        }

        // Delete dashboard permission records
        DB::table('permissions')
            ->where('name', 'like', 'dashboard.%')
            ->delete();

        // Clear cached permissions
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Re-create dashboard permissions (view, create, edit, delete, approve)
        $actions = ['view', 'create', 'edit', 'delete', 'approve'];
        foreach ($actions as $action) {
            DB::table('permissions')->insertOrIgnore([
                'name' => "dashboard.{$action}",
                'guard_name' => 'api',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
