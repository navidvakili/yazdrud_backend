<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\SiteNavigation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Site Navigation — منوهای پوسته سایت اصلی (Navigation Builder)
 *
 * This module defines the menus that the public site theme consumes.
 * Menus are stored per language (the language is determined by the main
 * multilingual system — Language::resolveRequest) and per location
 * (Header Main Menu, Footer Menu 1, Mobile Menu, ...).
 *
 * Public endpoints feed the main website theme; admin endpoints power the
 * Navigation Builder module in the management panel.
 */
class SiteNavigationController extends Controller
{
    /**
     * Normalize the incoming items payload to a JSON array.
     * Accepts either a raw JSON string or an already-decoded array.
     */
    private function normalizeItems(mixed $items): array
    {
        if (is_string($items)) {
            $decoded = json_decode($items, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($items) ? $items : [];
    }

    /**
     * Public: Get ALL active menus for the resolved language, keyed by location.
     * Single request — lets the public site theme render every menu at once.
     */
    public function publicIndex(Request $request): JsonResponse
    {
        $requestedLang = Language::resolveRequest($request);
        $menus = SiteNavigation::active()
            ->where('language', $requestedLang)
            ->orderBy('sort_order')
            ->orderBy('location')
            ->get();

        if ($menus->isEmpty() && $requestedLang !== Language::defaultLanguage()) {
            $menus = SiteNavigation::active()
                ->where('language', Language::defaultLanguage())
                ->orderBy('sort_order')
                ->orderBy('location')
                ->get();
        }

        $grouped = [];
        foreach ($menus as $menu) {
            $grouped[$menu->location] = [
                'id'         => $menu->id,
                'name'       => $menu->name,
                'slug'       => $menu->slug,
                'location'   => $menu->location,
                'version'    => $menu->version,
                'sort_order' => $menu->sort_order ?? 0,
                'items'      => $menu->items ?? [],
            ];
        }

        return response()->json([
            'data' => [
                'language' => $menus->isNotEmpty() ? $requestedLang : Language::defaultLanguage(),
                'menus'    => $grouped,
            ],
        ]);
    }

    /**
     * Public: Get ONE active menu by location for the resolved language.
     * e.g. /api/v1/navigation/header-main-menu/public
     */
    public function publicByLocation(Request $request, string $location): JsonResponse
    {
        $requestedLang = Language::resolveRequest($request);

        $menu = SiteNavigation::active()
            ->where('language', $requestedLang)
            ->where('location', $location)
            ->first();

        if (!$menu && $requestedLang !== Language::defaultLanguage()) {
            $menu = SiteNavigation::active()
                ->where('language', Language::defaultLanguage())
                ->where('location', $location)
                ->first();
        }

        if (!$menu) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'id'       => $menu->id,
                'name'     => $menu->name,
                'slug'     => $menu->slug,
                'location' => $menu->location,
                'version'  => $menu->version,
                'items'    => $menu->items ?? [],
            ],
        ]);
    }

    /**
     * Admin: List all navigation menus for the resolved language.
     */
    public function index(Request $request): JsonResponse
    {
        $lang = Language::resolveRequest($request);

        $query = SiteNavigation::where('language', $lang)
            ->orderBy('sort_order')
            ->orderBy('location');

        if ($request->filled('location')) {
            $query->where('location', $request->input('location'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $menus = $query->get();

        return response()->json([
            'data' => $menus,
        ]);
    }

    /**
     * Admin: Show a single navigation menu.
     */
    public function show(int $id): JsonResponse
    {
        $menu = SiteNavigation::find($id);

        if (!$menu) {
            return response()->json(['message' => 'منو یافت نشد'], 404);
        }

        return response()->json(['data' => $menu]);
    }

    /**
     * Admin: Get the menu for a location in the resolved language.
     * If it doesn't exist yet, a default empty menu is created automatically
     * (mirrors the slider-studio "current" behaviour so the builder works out of the box).
     */
    public function byLocation(Request $request, string $location): JsonResponse
    {
        $lang = Language::resolveRequest($request);

        $menu = SiteNavigation::where('language', $lang)
            ->where('location', $location)
            ->first();

        if (!$menu) {
            $menu = SiteNavigation::create([
                'language'   => $lang,
                'location'   => $location,
                'slug'       => Str::slug($location, '-'),
                'name'       => $this->defaultName($location),
                'items'      => [],
                'status'     => 'draft',
                'version'    => 1,
                'created_by' => $request->user()?->name ?? null,
            ]);
        }

        return response()->json(['data' => $menu]);
    }

    /**
     * Admin: Create a new navigation menu.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'location' => 'required|string|max:100',
            'name'     => 'required|string|max:255',
            'slug'     => 'nullable|string|max:191',
            'items'    => 'nullable',
            'status'   => 'nullable|in:active,draft,archived',
            'language' => 'nullable|string|max:10',
            'lang'     => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $lang = Language::resolveRequest($request);

        // Unique per language + location
        $exists = SiteNavigation::where('language', $lang)
            ->where('location', $request->input('location'))
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'منویی برای این موقعیت قبلاً ایجاد شده است'], 422);
        }

        $menu = SiteNavigation::create([
            'language'   => $lang,
            'location'   => $request->input('location'),
            'slug'       => $request->input('slug') ?: Str::slug($request->input('location'), '-'),
            'name'       => $request->input('name'),
            'items'      => $this->normalizeItems($request->input('items', [])),
            'status'     => $request->input('status', 'draft'),
            'version'    => 1,
            'sort_order' => (int) $request->input('sort_order', 0),
            'created_by' => $request->user()?->name ?? null,
        ]);

        return response()->json([
            'message' => 'منو با موفقیت ایجاد شد.',
            'data'    => $menu,
        ], 201);
    }

    /**
     * Admin: Update a navigation menu.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $menu = SiteNavigation::find($id);

        if (!$menu) {
            return response()->json(['message' => 'منو یافت نشد'], 404);
        }

        $validator = Validator::make($request->all(), [
            'location' => 'sometimes|string|max:100',
            'name'     => 'sometimes|string|max:255',
            'slug'     => 'nullable|string|max:191',
            'items'    => 'nullable',
            'status'   => 'nullable|in:active,draft,archived',
            'language' => 'nullable|string|max:10',
            'lang'     => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = [];

        if ($request->filled('location')) {
            $data['location'] = $request->input('location');
        }
        if ($request->filled('name')) {
            $data['name'] = $request->input('name');
        }
        if ($request->filled('slug')) {
            $data['slug'] = $request->input('slug');
        }
        if ($request->has('items')) {
            $data['items'] = $this->normalizeItems($request->input('items'));
        }
        if ($request->filled('status')) {
            $data['status'] = $request->input('status');
        }
        if ($request->has('sort_order')) {
            $data['sort_order'] = (int) $request->input('sort_order');
        }
        // Language can only move within the main multilingual system
        if ($request->filled('lang') || $request->filled('language')) {
            $data['language'] = Language::resolveRequest($request);
        }

        $menu->update($data);

        return response()->json([
            'message' => 'منو با موفقیت به‌روزرسانی شد.',
            'data'    => $menu->fresh(),
        ]);
    }

    /**
     * Admin: Delete a navigation menu.
     */
    public function destroy(int $id): JsonResponse
    {
        $menu = SiteNavigation::find($id);

        if (!$menu) {
            return response()->json(['message' => 'منو یافت نشد'], 404);
        }

        $menu->delete();

        return response()->json([
            'message' => 'منو با موفقیت حذف شد.',
        ]);
    }

    /**
     * Admin: Publish a menu — bumps the version and marks it active so the
     * public site theme picks it up immediately.
     */
    public function publish(int $id): JsonResponse
    {
        $menu = SiteNavigation::find($id);

        if (!$menu) {
            return response()->json(['message' => 'منو یافت نشد'], 404);
        }

        $menu->update([
            'status'  => 'active',
            'version' => $menu->version + 1,
        ]);

        return response()->json([
            'message' => 'منو با موفقیت منتشر شد.',
            'data'    => $menu->fresh(),
        ]);
    }

    /**
     * Default display name for a location (used when auto-creating a menu).
     */
    private function defaultName(string $location): string
    {
        return 'منوی ' . $location;
    }
}
