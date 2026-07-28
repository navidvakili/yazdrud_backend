<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsCategory;
use App\Models\RoleCategoryPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionController extends Controller
{
    /**
     * All module types that have category/group structures.
     * Each entry maps to a model that provides the groups.
     */
    private const CATEGORIZABLE_MODULES = [
        'news' => NewsCategory::class,
    ];

    /**
     * Get all permissions grouped by module, with Persian labels
     * and category/group structures (if any).
     */
    public function index(): JsonResponse
    {
        $permissions = Permission::where('guard_name', 'api')
            ->get()
            ->groupBy(function ($p) {
                return explode('.', $p->name)[0];
            });

        // Fetch module labels from the database
        $labels = DB::table('module_labels')
            ->pluck('label', 'module')
            ->toArray();

        // Fetch categories for modules that have group structures
        $categories = [];
        foreach (self::CATEGORIZABLE_MODULES as $module => $modelClass) {
            $categories[$module] = $modelClass::where('is_active', true)
                ->orderBy('ordering')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'color']);
        }

        return response()->json([
            'data'       => $permissions,
            'labels'     => $labels,
            'categories' => $categories,
        ]);
    }

    /**
     * Get all roles with their permissions, user counts, and category permissions.
     */
    public function roles(): JsonResponse
    {
        $roles = Role::where('guard_name', 'api')
            ->with('permissions')
            ->get();

        // Compute users_count manually because Spatie's Role::users()
        // joins on users.id, but our User model uses 'username' as PK.
        $roles->each(function ($role) {
            $role->users_count = \DB::table('model_has_roles')
                ->where('model_has_roles.role_id', $role->id)
                ->where('model_has_roles.model_type', \App\Models\User::class)
                ->count();

            // Attach category permissions for this role, grouped by category_type
            $role->category_permissions = RoleCategoryPermission::where('role_id', $role->id)
                ->get()
                ->groupBy('category_type')
                ->map(function ($items, $categoryType) {
                    return $items->groupBy('category_id')
                        ->map(function ($perms) {
                            return $perms->pluck('permission')->toArray();
                        });
                });
        });

        return response()->json([
            'data' => $roles,
        ]);
    }

    /**
     * Create a new role.
     */
    public function storeRole(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:125|unique:spatie_roles,name',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'api',
        ]);

        return response()->json([
            'message' => 'نقش جدید ایجاد شد',
            'data' => $role,
        ], 201);
    }

    /**
     * Update a role's permissions (module-level + category-level).
     */
    public function updateRole(Request $request, string $id): JsonResponse
    {
        $role = Role::where('id', $id)->where('guard_name', 'api')->firstOrFail();

        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
            'category_permissions' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // ── Sync module-level Spatie permissions ──
        $permissionNames = $request->permissions;
        $permissionIds = Permission::where('guard_name', 'api')
            ->whereIn('name', $permissionNames)
            ->pluck('id');

        $role->syncPermissions($permissionIds);

        // 🧠 CRITICAL: Spatie caches permissions for 24 hours. syncPermissions() on
        // the Role model (HasPermissions trait) does NOT clear the cache automatically.
        // Without this, users logging in after permission changes will see stale data
        // because $user->getAllPermissions() reads from cache, not the database.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // ── Sync category-level permissions ──
        // Expected format: { 'news': { '1': ['view','create'], '2': ['view'] } }
        if ($request->has('category_permissions')) {
            // Delete all existing category perms for this role
            RoleCategoryPermission::where('role_id', $role->id)->delete();

            $records = [];
            foreach ($request->category_permissions as $categoryType => $categories) {
                foreach ($categories as $categoryId => $perms) {
                    foreach ($perms as $permission) {
                        $records[] = [
                            'role_id'       => $role->id,
                            'category_type' => $categoryType,
                            'category_id'   => (int) $categoryId,
                            'permission'    => $permission,
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ];
                    }
                }
            }

            if (!empty($records)) {
                RoleCategoryPermission::insert($records);
            }
        }

        return response()->json([
            'message' => 'دسترسی‌های نقش بروزرسانی شد',
            'data' => $role->load('permissions'),
        ]);
    }

    /**
     * Delete a role.
     */
    public function destroyRole(string $id): JsonResponse
    {
        $role = Role::where('id', $id)->where('guard_name', 'api')->firstOrFail();

        // Prevent deleting admin role
        if ($role->name === 'admin') {
            return response()->json([
                'message' => 'امکان حذف نقش مدیر وجود ندارد',
            ], 403);
        }

        $role->delete();

        return response()->json([
            'message' => 'نقش حذف شد',
        ]);
    }

    /**
     * Assign a role to a user.
     */
    public function assignRole(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|exists:users,username',
            'role' => 'required|string|exists:spatie_roles,name',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = \App\Models\User::find($request->username);
        $user->assignRole($request->role);

        return response()->json([
            'message' => 'نقش به کاربر اختصاص یافت',
            'data' => $user->getAllRolesArray(),
        ]);
    }

    /**
     * Remove a role from a user.
     */
    public function removeRole(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|exists:users,username',
            'role' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = \App\Models\User::find($request->username);
        $user->removeRole($request->role);

        return response()->json([
            'message' => 'نقش از کاربر حذف شد',
            'data' => $user->getAllRolesArray(),
        ]);
    }

    /**
     * Get a user's roles and permissions.
     */
    public function userPermissions(string $username): JsonResponse
    {
        // Hide support user permissions from everyone
        if ($username === 'support') {
            return response()->json(['message' => 'کاربر یافت نشد'], 404);
        }

        $user = \App\Models\User::find($username);

        if (!$user) {
            return response()->json(['message' => 'کاربر یافت نشد'], 404);
        }

        return response()->json([
            'data' => [
                'username' => $user->username,
                'primary_role' => $user->role,
                'roles' => $user->getRoleNames()->toArray(),
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            ],
        ]);
    }
}
