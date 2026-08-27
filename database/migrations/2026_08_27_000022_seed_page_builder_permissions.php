<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Additive-only data seed for the newly-ported Page Builder module — same
 * pattern as 2026_08_27_000018_seed_gallery_forms_navigation_permissions
 * (see that migration for why this isn't a seeder class). Safe to run once;
 * a no-op on any re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        $module = 'page-builder';
        $label = 'صفحه ساز هوشمند';

        $support = Role::where('name', 'support')->where('guard_name', 'api')->first();
        $admin = Role::where('name', 'admin')->where('guard_name', 'api')->first();
        $editor = Role::where('name', 'editor')->where('guard_name', 'api')->first();

        foreach (['view', 'create', 'edit', 'delete', 'approve'] as $action) {
            Permission::firstOrCreate(['name' => "{$module}.{$action}", 'guard_name' => 'api']);
        }

        if ($support) {
            $support->givePermissionTo(
                Permission::where('guard_name', 'api')->where('name', 'like', "{$module}.%")->get()
            );
        }
        if ($admin) {
            $admin->givePermissionTo(
                Permission::whereIn('name', [
                    "{$module}.view", "{$module}.create", "{$module}.edit", "{$module}.delete", "{$module}.approve",
                ])->where('guard_name', 'api')->get()
            );
        }
        if ($editor) {
            $editor->givePermissionTo(
                Permission::whereIn('name', [
                    "{$module}.view", "{$module}.create", "{$module}.edit", "{$module}.approve",
                ])->where('guard_name', 'api')->get()
            );
        }

        DB::table('module_labels')->updateOrInsert(
            ['module' => $module],
            ['label' => $label, 'updated_at' => now(), 'created_at' => now()]
        );

        $url = "/{$module}";
        if (!DB::table('accesses')->where('url', $url)->exists()) {
            DB::table('accesses')->insert([
                'parent'   => null,
                'title'    => $label,
                'url'      => $url,
                'icon'     => 'fa fa-code',
                'roles'    => json_encode(['admin', 'editor', 'support']),
                'ordering' => 9,
                'active'   => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
    }
};
