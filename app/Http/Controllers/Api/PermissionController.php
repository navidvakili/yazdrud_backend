<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    /**
     * Get all permissions grouped by module.
     */
    public function index(): JsonResponse
    {
        $permissions = Permission::where('guard_name', 'api')
            ->get()
            ->groupBy(function ($p) {
                return explode('.', $p->name)[0];
            });

        return response()->json([
            'data' => $permissions,
        ]);
    }

    /**
     * Get all roles with their permissions.
     */
    public function roles(): JsonResponse
    {
        $roles = Role::where('guard_name', 'api')
            ->with('permissions')
            ->get();

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
     * Update a role's permissions.
     */
    public function updateRole(Request $request, string $id): JsonResponse
    {
        $role = Role::where('id', $id)->where('guard_name', 'api')->firstOrFail();

        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $permissionNames = $request->permissions;
        $permissionIds = Permission::where('guard_name', 'api')
            ->whereIn('name', $permissionNames)
            ->pluck('id');

        $role->syncPermissions($permissionIds);

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
