<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * List all users with optional search and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        // Search by username, fname, lname, email, mobile
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('fname', 'like', "%{$search}%")
                  ->orWhere('lname', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('mobile', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->filled('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->input('role'))
                  ->where('guard_name', 'api');
            });
        }

        $perPage = min((int) $request->input('per_page', 15), 50);
        $users = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Enrich each user with roles
        $users->getCollection()->transform(function ($user) {
            return $this->formatUser($user);
        });

        return response()->json($users);
    }

    /**
     * Get a single user with roles and permissions.
     */
    public function show(string $username): JsonResponse
    {
        $user = User::find($username);

        if (!$user) {
            return response()->json(['message' => 'کاربر یافت نشد'], 404);
        }

        return response()->json([
            'data' => $this->formatUserDetailed($user),
        ]);
    }

    /**
     * Create a new user.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:191|unique:users,username',
            'fname'    => 'required|string|max:100',
            'lname'    => 'required|string|max:100',
            'kodmeli'  => 'nullable|string|max:20',
            'mobile'   => 'nullable|string|max:20',
            'email'    => 'required|string|email|max:191|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role'     => 'nullable|string|exists:spatie_roles,name',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'username' => $request->username,
            'fname'    => $request->fname,
            'lname'    => $request->lname,
            'kodmeli'  => $request->kodmeli,
            'mobile'   => $request->mobile,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $request->input('role', 'user'),
        ]);

        // Assign role via Spatie
        $roleName = $request->input('role', 'user');
        if ($roleName) {
            $user->assignRole($roleName);
        }

        return response()->json([
            'message' => 'کاربر با موفقیت ایجاد شد',
            'data'    => $this->formatUserDetailed($user),
        ], 201);
    }

    /**
     * Update user profile info (not password).
     */
    public function update(Request $request, string $username): JsonResponse
    {
        $user = User::find($username);

        if (!$user) {
            return response()->json(['message' => 'کاربر یافت نشد'], 404);
        }

        $validator = Validator::make($request->all(), [
            'fname'   => 'sometimes|string|max:100',
            'lname'   => 'sometimes|string|max:100',
            'kodmeli' => 'sometimes|nullable|string|max:20',
            'mobile'  => 'sometimes|nullable|string|max:20',
            'email'   => 'sometimes|string|email|max:191|unique:users,email,' . $username . ',username',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update($request->only(['fname', 'lname', 'kodmeli', 'mobile', 'email']));

        return response()->json([
            'message' => 'اطلاعات کاربر با موفقیت به‌روزرسانی شد',
            'data'    => $this->formatUserDetailed($user->fresh()),
        ]);
    }

    /**
     * Admin: change a user's password (no current password required).
     */
    public function updatePassword(Request $request, string $username): JsonResponse
    {
        $user = User::find($username);

        if (!$user) {
            return response()->json(['message' => 'کاربر یافت نشد'], 404);
        }

        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'رمز عبور کاربر با موفقیت تغییر یافت',
        ]);
    }

    /**
     * Delete a user.
     */
    public function destroy(string $username): JsonResponse
    {
        $user = User::find($username);

        if (!$user) {
            return response()->json(['message' => 'کاربر یافت نشد'], 404);
        }

        // Prevent deleting the support/developer user
        if ($user->hasRole('support')) {
            return response()->json([
                'message' => 'امکان حذف کاربر پشتیبان وجود ندارد',
            ], 403);
        }

        // Revoke all tokens before deleting
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'کاربر با موفقیت حذف شد',
        ]);
    }

    /**
     * Assign a role to a user (via admin panel).
     */
    public function assignRole(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|exists:users,username',
            'role'     => 'required|string|exists:spatie_roles,name',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::find($request->username);
        $user->assignRole($request->role);

        return response()->json([
            'message' => 'نقش به کاربر اختصاص یافت',
            'data'    => [
                'roles'       => $user->getRoleNames()->toArray(),
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            ],
        ]);
    }

    /**
     * Remove a role from a user.
     */
    public function removeRole(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|exists:users,username',
            'role'     => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::find($request->username);
        $user->removeRole($request->role);

        return response()->json([
            'message' => 'نقش از کاربر حذف شد',
            'data'    => [
                'roles'       => $user->getRoleNames()->toArray(),
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            ],
        ]);
    }

    /**
     * Get all available roles (for the role assignment dropdown).
     */
    public function roles(): JsonResponse
    {
        $roles = Role::where('guard_name', 'api')
            ->withCount('permissions')
            ->get()
            ->map(fn ($role) => [
                'id'              => $role->id,
                'name'            => $role->name,
                'permissions_count' => $role->permissions_count,
            ]);

        return response()->json([
            'data' => $roles,
        ]);
    }

    // ===== Private Helpers =====

    /**
     * Format a user for list view (basic info + roles).
     */
    private function formatUser(User $user): array
    {
        return [
            'username'   => $user->username,
            'fname'      => $user->fname,
            'lname'      => $user->lname,
            'full_name'  => $user->getName(),
            'email'      => $user->email,
            'mobile'     => $user->mobile,
            'role'       => $user->role,
            'roles'      => $user->getRoleNames()->toArray(),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    /**
     * Format a user for detail/edit view (includes permissions).
     */
    private function formatUserDetailed(User $user): array
    {
        return [
            'username'   => $user->username,
            'fname'      => $user->fname,
            'lname'      => $user->lname,
            'full_name'  => $user->getName(),
            'kodmeli'    => $user->kodmeli,
            'mobile'     => $user->mobile,
            'email'      => $user->email,
            'role'       => $user->role,
            'roles'      => $user->getRoleNames()->toArray(),
            'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
