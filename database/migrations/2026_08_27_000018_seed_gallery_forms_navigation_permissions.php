<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Additive-only data seed for the three newly-ported modules (gallery, forms,
 * navigation): permissions, role grants, module_labels, and the admin sidebar
 * (accesses) entries. Deliberately NOT a seeder class — seeders in this
 * project (RolesAndPermissionsSeeder/ModuleLabelsSeeder/AccessesSeeder) are
 * sync/truncate-oriented and unsafe to run wholesale against a populated
 * production database (AccessesSeeder truncates `accesses`; ModuleLabelsSeeder
 * deletes labels not in its own list, e.g. this project's own
 * county-projects/hero-slides). Every write below is firstOrCreate/
 * updateOrInsert/exists-guarded, so this migration is safe to run once and
 * is a no-op on any re-run (including `migrate:fresh` replays).
 */
return new class extends Migration
{
    public function up(): void
    {
        $modules = [
            'gallery'    => ['label' => 'مدیریت دارایی‌های دیجیتال',            'icon' => 'fa fa-box',       'ordering' => 6],
            'forms'      => ['label' => 'فرم‌ساز و پرسشنامه‌ساز هوشمند',        'icon' => 'fa fa-file-text', 'ordering' => 7],
            'navigation' => ['label' => 'مدیریت و ساخت ناوبری',                 'icon' => 'fa fa-sitemap',   'ordering' => 8],
        ];

        $support = Role::where('name', 'support')->where('guard_name', 'api')->first();
        $admin = Role::where('name', 'admin')->where('guard_name', 'api')->first();
        $editor = Role::where('name', 'editor')->where('guard_name', 'api')->first();

        foreach ($modules as $module => $meta) {
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
                ['label' => $meta['label'], 'updated_at' => now(), 'created_at' => now()]
            );

            $url = "/{$module}";
            if (!DB::table('accesses')->where('url', $url)->exists()) {
                DB::table('accesses')->insert([
                    'parent'   => null,
                    'title'    => $meta['label'],
                    'url'      => $url,
                    'icon'     => $meta['icon'],
                    'roles'    => json_encode(['admin', 'editor', 'support']),
                    'ordering' => $meta['ordering'],
                    'active'   => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Deliberately no-op: reversing role/permission grants and menu entries
     * for a live production system is riskier than leaving them in place if
     * this migration is ever rolled back (e.g. an admin may have since
     * customized these accesses rows). The schema-only migrations for these
     * modules remain independently reversible.
     */
    public function down(): void
    {
    }
};
