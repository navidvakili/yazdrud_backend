<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Access;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NavigationController extends Controller
{
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

        // Collect all roles: primary role + roles from the roles table
        $primaryRole = $user->role;
        $roles = array_values($user->roles);
        if (!in_array($primaryRole, $roles)) {
            $roles[] = $primaryRole;
        }

        $links = $this->buildMenu($roles);

        return response()->json([
            'data' => $links,
        ]);
    }

    /**
     * Get all available roles in the system.
     */
    public function roles(Request $request): JsonResponse
    {
        $user = $request->user();
        $roles = $user->roles;

        return response()->json([
            'data' => [
                'primary_role' => $user->role,
                'all_roles' => array_values($roles),
            ],
        ]);
    }

    /**
     * Get all accesses (permissions) available to the user.
     */
    public function permissions(Request $request): JsonResponse
    {
        $user = $request->user();
        $primaryRole = $user->role;
        $roles = array_values($user->roles);
        if (!in_array($primaryRole, $roles)) {
            $roles[] = $primaryRole;
        }

        // Get all access items matching the user's roles (both top-level and children)
        $allAccesses = Access::where(function ($query) use ($roles) {
                foreach ($roles as $role) {
                    $query->orWhere('roles', 'like', '%|' . $role . '|%')
                        ->orWhere('roles', 'like', $role . '|%')
                        ->orWhere('roles', 'like', '%|' . $role)
                        ->orWhere('roles', $role);
                }
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

        // Get top-level menu items
        $topItems = $this->getAccessesByRoles($roles, 'top');

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
    private function getAccessesByRoles(array $roles, string|int $parent): mixed
    {
        return Access::where(function ($query) use ($roles) {
                foreach ($roles as $role) {
                    $query->orWhere('roles', 'like', '%|' . $role . '|%')
                        ->orWhere('roles', 'like', $role . '|%')
                        ->orWhere('roles', 'like', '%|' . $role)
                        ->orWhere('roles', $role);
                }
            })
            ->where(['active' => 1, 'parent' => $parent])
            ->orderBy('ordering', 'asc')
            ->get();
    }
}
