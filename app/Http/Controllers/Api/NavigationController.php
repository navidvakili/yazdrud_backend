<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Access;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NavigationController extends Controller
{
    /**
     * Persian labels for all roles (مطابق Enums.php پروژه قدیمی).
     */
    const ROLE_LABELS = [
        'admin'            => 'مدیر سامانه',
        'editor'           => 'ویرایشگر محتوا',
        'user'             => 'کاربر',
    ];

    /**
     * Get the navigation menu for the authenticated user.
     * Based on the original Navigation::links() from the legacy portal.
     *
     * Builds a hierarchical menu by:
     * 1. Getting all roles for the authenticated user
     * 2. Querying the accesses table for menu items matching those roles
     * 3. Organizing them as parent → children (top-level items with children)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only use the current active role (user.role) — like old Navigation::links()
        // This ensures menus change when the user switches role.
        $currentRole = $user->role;

        $links = $this->buildMenu([$currentRole]);

        return response()->json([
            'data' => $links,
        ]);
    }

    /**
     * Get the authenticated user's roles with Persian labels.
     * مشابه متد Enums::getMyRoles() در پروژه قدیمی.
     */
    public function roles(Request $request): JsonResponse
    {
        $user = $request->user();
        $primaryRole = $user->role;

        // Get all Spatie roles for the user (api guard)
        $spatieRoleNames = $user->getRoleNames()->toArray();
        $allRoles = [];
        $id = 0;

        foreach ($spatieRoleNames as $roleName) {
            $label = self::ROLE_LABELS[$roleName] ?? $roleName;

            $allRoles[] = [
                'id'     => ++$id,
                'role'   => $roleName,
                'label'  => $label,
                'active' => ($primaryRole === $roleName) ? 1 : 0,
            ];
        }

        return response()->json([
            'data' => [
                'primary_role' => $primaryRole,
                'all_roles'    => $allRoles,
            ],
        ]);
    }

    /**
     * Get all accesses (permissions) available to the user.
     */
    public function permissions(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentRole = $user->role;

        // Get all access items matching the user's current active role
        $allAccesses = Access::where(function ($query) use ($currentRole) {
                $query->whereJsonContains('roles', $currentRole);
            })
            ->where('active', 1)
            ->orderBy('parent', 'asc')
            ->orderBy('ordering', 'asc')
            ->get();

        return response()->json([
            'data' => $allAccesses,
        ]);
    }

    /**
     * Build hierarchical menu from accesses table based on user roles.
     */
    private function buildMenu(array $roles): array
    {
        $links = [];

        // Get top-level menu items (parent is null)
        $topItems = $this->getAccessesByRoles($roles, null);

        foreach ($topItems as $row) {
            // Get children for this parent
            $children = $this->getAccessesByRoles($roles, $row->id);

            $item = [
                'id' => $row->id,
                'title' => $row->title,
                'url' => $row->url,
                'icon' => $row->icon,
                'ordering' => $row->ordering,
                'children' => [],
            ];

            foreach ($children as $child) {
                $item['children'][] = [
                    'title' => $child->title,
                    'url' => $child->url,
                    'icon' => $child->icon,
                ];
            }

            $links[] = $item;
        }

        return $links;
    }

    /**
     * Query accesses table filtered by roles and parent.
     */
    private function getAccessesByRoles(array $roles, string|int|null $parent): mixed
    {
        return Access::where(function ($query) use ($roles) {
                foreach ($roles as $role) {
                    $query->orWhereJsonContains('roles', $role);
                }
            })
            ->where(['active' => 1, 'parent' => $parent])
            ->orderBy('ordering', 'asc')
            ->get();
    }
}
