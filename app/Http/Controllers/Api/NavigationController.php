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
     *
     * Builds a hierarchical menu by:
     * 1. Querying ALL active access items (unfiltered by role)
     * 2. For each item, checking if the user has the corresponding
     *    Spatie view permission (e.g. services.view, news.view)
     * 3. If no Spatie permission mapping exists, falling back to
     *    the old role-based filter (accesses.roles JSON column)
     *
     * This bridges the legacy accesses table with the new Spatie
     * permission system: admins can assign Spatie permissions to
     * any role and the menu updates automatically without needing
     * to edit the accesses table manually.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();
        $currentRole = $user->role;

        $links = $this->buildMenu($currentRole, $userPermissions);

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
     * Build hierarchical menu from accesses table using Spatie permissions.
     *
     * For each access item:
     * 1. If a Spatie view permission mapping exists (e.g. /services → services.view),
     *    the item is visible ONLY if the user has that permission.
     * 2. If NO mapping exists (e.g. URL is '#'), falls back to the
     *    legacy role-based check (accesses.roles JSON contains $currentRole).
     *
     * Special case for parent items (URL = '#' or no children URL mapping):
     * even if the parent itself fails the check, it will still be shown
     * if ANY of its children pass the Spatie permission check.
     */
    private function buildMenu(string $currentRole, array $userPermissions): array
    {
        $links = [];

        // Get ALL active top-level menu items (no role pre-filtering)
        $topItems = Access::where('active', 1)
            ->whereNull('parent')
            ->orderBy('ordering', 'asc')
            ->get();

        foreach ($topItems as $row) {
            // Get all active children (no role pre-filtering)
            $children = Access::where('active', 1)
                ->where('parent', $row->id)
                ->orderBy('ordering', 'asc')
                ->get();

            // Filter children by Spatie permission or legacy role
            $visibleChildren = [];
            foreach ($children as $child) {
                if ($this->userCanSeeItem($child, $currentRole, $userPermissions)) {
                    $visibleChildren[] = [
                        'title' => $child->title,
                        'url' => $child->url,
                        'icon' => $child->icon,
                    ];
                }
            }

            // Does the user have access to this parent item?
            $parentAccessible = $this->userCanSeeItem($row, $currentRole, $userPermissions);

            if ($parentAccessible) {
                // Parent is accessible directly — include it if it has
                // visible children or is a leaf
                if ($children->isEmpty() || $visibleChildren !== []) {
                    $links[] = [
                        'id' => $row->id,
                        'title' => $row->title,
                        'url' => $row->url,
                        'icon' => $row->icon,
                        'ordering' => $row->ordering,
                        'children' => $visibleChildren,
                    ];
                }
            } else {
                // Parent NOT directly accessible (e.g. url='#' with no matching
                // Spatie permission, and role not in accesses.roles).
                // Still show it if it has visible children — this handles
                // the case where the parent is a category wrapper like
                // 'خدمات الکترونیکی' (url=#) whose children like /services,
                // /urban-planning etc. are individually controlled by Spatie.
                if ($visibleChildren !== []) {
                    $links[] = [
                        'id' => $row->id,
                        'title' => $row->title,
                        'url' => $row->url,
                        'icon' => $row->icon,
                        'ordering' => $row->ordering,
                        'children' => $visibleChildren,
                    ];
                }
            }
        }

        return $links;
    }

    /**
     * Check if a user can see a given access item.
     *
     * Priority:
     * 1. If a Spatie permission mapping exists for the item's URL,
     *    check the user's Spatie permissions.
     * 2. Otherwise, fall back to the legacy roles JSON column check.
     */
    private function userCanSeeItem(Access $item, string $currentRole, array $userPermissions): bool
    {
        $permissionName = $this->urlToViewPermission($item->url);

        if ($permissionName !== null) {
            // Spatie mapping exists — use it
            return in_array($permissionName, $userPermissions, true);
        }

        // Fallback: legacy role-based check
        $itemRoles = $item->roles ?? [];
        return in_array($currentRole, $itemRoles, true);
    }

    /**
     * Map access item URLs to their corresponding Spatie view permission names.
     *
     * This is the bridge between the legacy accesses table URLs and the
     * Spatie permission system. Items without a mapping fall back to
     * the old role-based filter (accesses.roles JSON column).
     */
    private function urlToViewPermission(string $url): ?string
    {
        $map = [
            '/users'           => 'users.view',
            '/sessions'        => 'sessions.view',
            '/news'            => 'news.view',
            '/library'         => 'library.view',
        ];

        return $map[$url] ?? null;
    }
}
