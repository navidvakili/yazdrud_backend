<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Access;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NavigationController extends Controller
{
    /**
     * Persian labels for all roles (مطابق Enums.php پروژه قدیمی).
     */
    const ROLE_LABELS = [
        'uni'              => 'کارشناس تحصیلات تکمیلی',
        'headgroup'        => 'مدیرگروه',
        'amouzesh'         => 'مدیر آموزش',
        'amouzesh_karshanes' => 'کارشناس آموزش',
        'pajouheshi'       => 'معاون پژوهشی',
        'service'          => 'معاون آموزشی',
        'teacher'          => 'استاد',
        'student'          => 'دانشجو',
        'pajouheshik'      => 'کارشناس پژوهشی',
        'pajouheshiphd'    => 'پژوهشی دکتری',
        'college'          => 'رئیس دانشکده',
        'group'            => 'کارشناس گروه',
        'library'          => 'مسئول کتابخانه',
        'mali'             => 'کارشناس مالی',
        'amouzeshkol'      => 'آموزش کل',
        'pajouheshikol'    => 'کارشناس پژوهشی کل',
        'itman'            => 'فناوری اطلاعات',
        'shahrie_admin'    => 'کارشناس مسئول شهریه',
        'shahrie'          => 'کارشناس شهریه',
        'resalat'          => 'مسئول رسالت',
        'pr'               => 'مدیر روابط عمومی',
        'pr2'              => 'کارشناس روابط عمومی',
        'admin'            => 'مدیر سامانه',
        'newstudent'       => 'پذیرفته شده',
        'arshad'           => 'مسئول تحصیلات تکمیلی',
        'farhangi'         => 'مسئول دانشجویی فرهنگی',
        'farhangik'        => 'کارشناس دانشجویی فرهنگی',
        'farhangik2'       => 'کارشناس رویدادهای فرهنگی',
        'exhibition'       => 'مسئول نمایشگاه',
        'khabgah'          => 'مسئول خوابگاه',
        'phduser'          => 'متقاضی مصاحبه دکتری',
        'phduser4001'      => 'متقاضی مصاحبه دکتری',
        'phduser401'       => 'متقاضی مصاحبه دکتری',
        'phduser403'       => 'متقاضی مصاحبه دکتری',
        'phduser404'       => 'متقاضی مصاحبه دکتری',
        'nezarat'          => 'کارشناس نظارت',
        'refah'            => 'صندوق رفاه دانشجویی',
        'moavenp'          => 'معاون پشتیبانی',
        'moshavere'        => 'مسئول مرکز مشاوره',
        'editor_thesis'    => 'ویراستار پایان نامه',
        'refahi'           => 'امور رفاهی',
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

        // Get all roles from the roles table
        $roleRecords = Role::where('username', $user->username)->get();
        $allRoles = [];

        foreach ($roleRecords as $row) {
            $roleName = $row->role;
            $label = self::ROLE_LABELS[$roleName] ?? $roleName;

            $allRoles[] = [
                'id'     => $row->id,
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
                $query->where('roles', 'like', '%|' . $currentRole . '|%')
                    ->orWhere('roles', 'like', $currentRole . '|%')
                    ->orWhere('roles', 'like', '%|' . $currentRole)
                    ->orWhere('roles', $currentRole);
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
