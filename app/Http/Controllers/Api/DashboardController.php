<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPinnedMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DashboardController extends Controller
{
    /**
     * Get the list of pinned menu IDs for the authenticated user.
     */
    public function pinnedMenus(Request $request): JsonResponse
    {
        $pinned = UserPinnedMenu::where('username', $request->user()->username)
            ->pluck('menu_id');

        return response()->json([
            'data' => $pinned,
        ]);
    }

    /**
     * Pin a menu item for the authenticated user.
     */
    public function pin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'menu_id' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $username = $request->user()->username;
        $menuId = $request->menu_id;

        // Check if already pinned
        $exists = UserPinnedMenu::where('username', $username)
            ->where('menu_id', $menuId)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'این آیتم قبلاً پین شده است',
            ], 409);
        }

        UserPinnedMenu::create([
            'username' => $username,
            'menu_id' => $menuId,
        ]);

        return response()->json([
            'message' => 'آیتم با موفقیت پین شد',
        ], 201);
    }

    /**
     * Unpin a menu item for the authenticated user.
     */
    public function unpin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'menu_id' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $username = $request->user()->username;
        $menuId = $request->menu_id;

        $deleted = UserPinnedMenu::where('username', $username)
            ->where('menu_id', $menuId)
            ->delete();

        if ($deleted === 0) {
            return response()->json([
                'message' => 'این آیتم پین نشده است',
            ], 404);
        }

        return response()->json([
            'message' => 'آیتم با موفقیت از پین خارج شد',
        ]);
    }
}
