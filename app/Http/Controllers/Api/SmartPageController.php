<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\SmartPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SmartPageController extends Controller
{
    // ==================== PUBLIC ENDPOINTS ====================

    /**
     * Public list of published pages.
     * GET /smart-pages/public?lang=fa
     * Returns a plain array (id, title, slug, seo, updated_at).
     */
    public function publicIndex(Request $request): JsonResponse
    {
        $pages = SmartPage::query()
            ->where('status', 'published')
            ->where('language', Language::resolve($request->input('lang')))
            ->orderBy('updated_at', 'desc')
            ->get(['id', 'title', 'slug', 'seo', 'updated_at']);

        return response()->json($pages);
    }

    /**
     * Public single page by slug (full schema for rendering).
     * GET /smart-pages/slug/{slug}/public
     * For parent pages, a lightweight `children` list is also returned.
     */
    public function publicShowBySlug(Request $request, string $slug): JsonResponse
    {
        $page = SmartPage::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->where('language', Language::resolve($request->input('lang')))
            ->first();

        if (!$page) {
            return response()->json(['message' => 'صفحه یافت نشد'], 404);
        }

        $data = $this->formatPage($page);

        // زیرصفحه‌های منتشرشده (درختی — همهٔ نسل‌ها) — برای فهرست خودکار انتهای صفحهٔ والد و ویجت «لیست زیرصفحه‌ها»
        $data['children'] = $this->publicChildrenTree($page);

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Public single CHILD page under a parent (by child slug OR numeric id).
     * GET /smart-pages/{parentSlug}/{childKey}/public
     * Verifies that the child really belongs to the given parent slug.
     */
    public function publicShowChild(Request $request, string $parentSlug, string $childKey): JsonResponse
    {
        $parent = SmartPage::query()
            ->where('slug', $parentSlug)
            ->where('status', 'published')
            ->where('language', Language::resolve($request->input('lang')))
            ->first();

        if (!$parent) {
            return response()->json(['message' => 'صفحه یافت نشد'], 404);
        }

        // childKey: اول با slug، بعد (اگر عددی باشد) با شناسهٔ عددی
        $child = SmartPage::query()
            ->where('parent_id', $parent->id)
            ->where('status', 'published')
            ->where('language', $parent->language)
            ->where(function ($q) use ($childKey) {
                $q->where('slug', $childKey);
                if (ctype_digit($childKey)) {
                    $q->orWhere('id', (int) $childKey);
                }
            })
            ->first();

        if (!$child) {
            return response()->json(['message' => 'صفحه یافت نشد'], 404);
        }

        $data = $this->formatPage($child);
        // زیرصفحه‌های منتشرشدهٔ خودِ این زیرصفحه (درختی)
        $data['children'] = $this->publicChildrenTree($child);

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Public list of published CHILD pages of a parent (by parent slug).
     * GET /smart-pages/{parentSlug}/children/public
     */
    public function publicChildren(Request $request, string $parentSlug): JsonResponse
    {
        $parent = SmartPage::query()
            ->where('slug', $parentSlug)
            ->where('status', 'published')
            ->where('language', Language::resolve($request->input('lang')))
            ->first();

        if (!$parent) {
            return response()->json(['message' => 'صفحه یافت نشد'], 404);
        }

        return response()->json(
            $this->publicChildrenTree($parent)
        );
    }

    // ==================== ADMIN LIST / DETAIL ====================

    /**
     * Admin list of pages with search, filter, and pagination.
     * GET /smart-pages?search=&status=&page=&per_page=&lang=
     */
    public function index(Request $request): JsonResponse
    {
        $query = SmartPage::query();

        $query->where('language', Language::resolve($request->input('lang')));

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $query->orderBy('updated_at', 'desc');

        $perPage = min((int) $request->input('per_page', 15), 500);
        $pages = $query->paginate($perPage);

        // Lightweight list rows (no full schema to keep the payload small)
        $pages->getCollection()->transform(fn (SmartPage $page) => [
            'id'         => $page->id,
            'title'      => $page->title,
            'slug'       => $page->slug,
            'parent_id'  => $page->parent_id,
            'sort_order' => $page->sort_order,
            'status'     => $page->status,
            'seo'        => $page->seo,
            'language'   => $page->language,
            'author_name' => $page->author_name,
            'author_username' => $page->author_username,
            'updated_at' => $page->updated_at,
            'created_at' => $page->created_at,
            'published_at' => $page->published_at?->toDateTimeString(),
        ]);

        return response()->json($pages);
    }

    /**
     * Admin list of CHILD pages of a page (lightweight, for the canvas widget).
     * GET /smart-pages/{id}/children
     */
    public function children(int $id): JsonResponse
    {
        $page = SmartPage::find($id);

        if (!$page) {
            return response()->json(['message' => 'صفحه یافت نشد'], 404);
        }

        return response()->json(
            $this->formatChildrenList($page->children()->get())
        );
    }

    /**
     * Admin TREE of ALL descendant pages (recursive, for the child-pages manager dialog).
     * GET /smart-pages/{id}/children/tree
     */
    public function childrenTree(int $id): JsonResponse
    {
        $page = SmartPage::find($id);

        if (!$page) {
            return response()->json(['message' => 'صفحه یافت نشد'], 404);
        }

        return response()->json($this->buildChildrenTree($page));
    }

    /**
     * Admin single page detail (full schema).
     * GET /smart-pages/{id}
     */
    public function show(int $id): JsonResponse
    {
        $page = SmartPage::find($id);

        if (!$page) {
            return response()->json(['message' => 'صفحه یافت نشد'], 404);
        }

        return response()->json([
            'data' => $this->formatPage($page),
        ]);
    }

    // ==================== ADMIN CRUD ====================

    /**
     * Create a new smart page.
     * POST /smart-pages
     */
    public function store(Request $request): JsonResponse
    {
        $lang = Language::resolveRequest($request);

        $validated = $request->validate([
            'title'      => 'required|string|max:300',
            'slug'       => [
                'required', 'string', 'max:191', 'regex:/^[a-z0-9\-]+$/',
                Rule::unique('smart_pages', 'slug')->where(fn ($q) => $q->where('language', $lang)),
            ],
            'status'     => 'sometimes|required|in:published,draft',
            'language'   => 'nullable|string|max:10',
            'lang'       => 'nullable|string|max:10',
            'seo'        => 'nullable|array',
            'schema'     => 'required|array',
            'parent_id'  => 'nullable|integer|exists:smart_pages,id',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $user = $request->user();

        if (!empty($validated['parent_id'])) {
            $parent = SmartPage::find($validated['parent_id']);
            if (!$parent || $parent->language !== $lang) {
                return response()->json(['message' => 'صفحهٔ والد نامعتبر است'], 422);
            }
        }

        // If user does NOT have approve permission, force status to draft
        if (!$user->can('page-builder.approve') && ($validated['status'] ?? 'draft') === 'published') {
            $validated['status'] = 'draft';
        }

        $page = SmartPage::create([
            'language'        => $lang,
            'title'           => $validated['title'],
            'slug'            => $validated['slug'],
            'parent_id'       => $validated['parent_id'] ?? null,
            'sort_order'      => $validated['sort_order'] ?? 0,
            'status'          => $validated['status'] ?? 'draft',
            'seo'             => $validated['seo'] ?? null,
            'schema'          => $validated['schema'],
            'author_username' => $user->username,
            'author_name'     => trim(($user->fname ?? '') . ' ' . ($user->lname ?? '')),
            'author_role'     => $user->role ?? null,
            'published_at'    => ($validated['status'] ?? 'draft') === 'published' ? now() : null,
        ]);

        return response()->json([
            'message' => 'صفحه با موفقیت ایجاد شد',
            'data'    => $this->formatPage($page),
        ], 201);
    }

    /**
     * Duplicate a page into another language — since each language variant
     * is an independent page (schema, SEO, everything is copied so the admin
     * has a working starting point to translate), this is the supported way
     * to "create the same page in another language" without rebuilding the
     * layout from scratch.
     *
     * The copy is always saved as a draft, with no parent (cross-language
     * page trees aren't linked automatically — the admin re-attaches a
     * parent, if any, from the new language's own pages).
     *
     * POST /smart-pages/{id}/duplicate  { lang: "en" }
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        $source = SmartPage::find($id);

        if (!$source) {
            return response()->json(['message' => 'صفحه یافت نشد'], 404);
        }

        $validated = $request->validate([
            'language' => 'nullable|string|max:10',
            'lang'     => 'nullable|string|max:10',
        ]);
        $targetLang = Language::resolveRequest($request);

        if ($targetLang === $source->language) {
            return response()->json(['message' => 'زبان مقصد باید با زبان صفحهٔ فعلی متفاوت باشد'], 422);
        }

        // Keep the source's slug if free in the target language, otherwise suffix it.
        $slug = $source->slug;
        if (SmartPage::where('slug', $slug)->where('language', $targetLang)->exists()) {
            $slug = $slug . '-' . $targetLang;
            $suffixed = $slug;
            $n = 2;
            while (SmartPage::where('slug', $suffixed)->where('language', $targetLang)->exists()) {
                $suffixed = $slug . '-' . $n;
                $n++;
            }
            $slug = $suffixed;
        }

        // Backfill a translation group on the source the first time it's duplicated,
        // so both records end up sharing the same group value.
        $group = $source->translation_group ?: (string) Str::uuid();
        if (!$source->translation_group) {
            $source->update(['translation_group' => $group]);
        }

        $user = $request->user();

        $copy = SmartPage::create([
            'language'          => $targetLang,
            'translation_group' => $group,
            'title'             => $source->title,
            'slug'              => $slug,
            'parent_id'         => null,
            'sort_order'        => 0,
            'status'            => 'draft',
            'seo'               => $source->seo,
            'schema'            => $source->schema,
            'author_username'   => $user->username,
            'author_name'       => trim(($user->fname ?? '') . ' ' . ($user->lname ?? '')),
            'author_role'       => $user->role ?? null,
            'published_at'      => null,
        ]);

        return response()->json([
            'message' => 'نسخهٔ صفحه در زبان مقصد ایجاد شد — اکنون می‌توانید محتوای آن را ترجمه کنید',
            'data'    => $this->formatPage($copy),
        ], 201);
    }

    /**
     * Update a smart page.
     * PUT /smart-pages/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $page = SmartPage::find($id);

        if (!$page) {
            return response()->json(['message' => 'صفحه یافت نشد'], 404);
        }

        // Slug uniqueness is scoped per language — if the request is also
        // changing the page's language, validate against the NEW language.
        $targetLang = ($request->filled('lang') || $request->filled('language'))
            ? Language::resolveRequest($request)
            : $page->language;

        $validated = $request->validate([
            'title'      => 'sometimes|required|string|max:300',
            'slug'       => [
                'sometimes', 'required', 'string', 'max:191', 'regex:/^[a-z0-9\-]+$/',
                Rule::unique('smart_pages', 'slug')->where(fn ($q) => $q->where('language', $targetLang))->ignore($id),
            ],
            'status'     => 'sometimes|required|in:published,draft',
            'language'   => 'nullable|string|max:10',
            'lang'       => 'nullable|string|max:10',
            'seo'        => 'nullable|array',
            'schema'     => 'sometimes|required|array',
            'parent_id'  => 'nullable|integer|exists:smart_pages,id',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // جلوگیری از انتخاب خود به عنوان والد (self-parenting) و حلقهٔ مرجع (circular)
        if (array_key_exists('parent_id', $validated)) {
            $newParentId = $validated['parent_id'] ?? null;
            if ($newParentId !== null) {
                if ((int) $newParentId === $id) {
                    return response()->json(['message' => 'یک صفحه نمی‌تواند والد خودش باشد'], 422);
                }
                $ancestor = SmartPage::find($newParentId);
                while ($ancestor && $ancestor->parent_id) {
                    if ((int) $ancestor->parent_id === $id) {
                        return response()->json(['message' => 'چرخهٔ نامعتبر در ساختار والد/فرزند'], 422);
                    }
                    $ancestor = SmartPage::find($ancestor->parent_id);
                }
            }
        }

        if ($request->filled('lang') || $request->filled('language')) {
            $validated['language'] = Language::resolveRequest($request);
        }

        // If user does NOT have approve permission, prevent publishing
        if (isset($validated['status']) && $validated['status'] === 'published') {
            if (!$request->user()->can('page-builder.approve')) {
                $validated['status'] = 'draft';
            }
        }

        // Reset published_at on explicit publish / status change
        if (isset($validated['status'])) {
            $validated['published_at'] = $validated['status'] === 'published' ? now() : null;
        }

        $page->update($validated);

        return response()->json([
            'message' => 'صفحه با موفقیت به‌روزرسانی شد',
            'data'    => $this->formatPage($page->fresh()),
        ]);
    }

    /**
     * Delete a smart page.
     * DELETE /smart-pages/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $page = SmartPage::find($id);

        if (!$page) {
            return response()->json(['message' => 'صفحه یافت نشد'], 404);
        }

        // حذف صفحهٔ والد، زیرصفحه‌هایش را هم حذف می‌کند (cascade) — اطلاع‌رسانی در پاسخ
        $childCount = $page->children()->count();
        $page->delete();

        return response()->json([
            'message' => $childCount > 0
                ? "صفحه و {$childCount} زیرصفحهٔ آن با موفقیت حذف شد"
                : 'صفحه با موفقیت حذف شد',
        ]);
    }

    // ==================== FORMATTERS ====================

    public function formatPage(SmartPage $page): array
    {
        return [
            'id'          => $page->id,
            'title'       => $page->title,
            'slug'        => $page->slug,
            'parent_id'   => $page->parent_id,
            'parent_slug' => $page->parent_id ? $page->parent?->slug : null,
            'sort_order'  => $page->sort_order,
            'status'      => $page->status,
            'seo'         => $page->seo,
            'schema'      => $page->schema,
            'language'    => $page->language,
            'translation_group' => $page->translation_group,
            'author_name' => $page->author_name,
            'author_username' => $page->author_username,
            'author_role' => $page->author_role,
            'published_at' => $page->published_at?->toDateTimeString(),
            'created_at'  => $page->created_at?->toDateTimeString(),
            'updated_at'  => $page->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * Lightweight children rows for list endpoints and the child-pages widget.
     */
    protected function formatChildrenList($children): array
    {
        return $children->map(fn (SmartPage $page) => $this->formatChildrenRow($page))->values()->all();
    }

    /**
     * Lightweight single children row (id, title, slug, ...).
     */
    protected function formatChildrenRow(SmartPage $page): array
    {
        return [
            'id'           => $page->id,
            'title'        => $page->title,
            'slug'         => $page->slug,
            'parent_id'    => $page->parent_id,
            'sort_order'   => $page->sort_order,
            'status'       => $page->status,
            'language'     => $page->language,
            'published_at' => $page->published_at?->toDateTimeString(),
            'updated_at'   => $page->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * Recursively build the descendant tree of a page (each node carries its own children).
     */
    protected function buildChildrenTree(SmartPage $page): array
    {
        return $page->children()->get()
            ->map(fn (SmartPage $child) => $this->formatChildrenRow($child) + [
                'children' => $this->buildChildrenTree($child),
            ])
            ->values()
            ->all();
    }

    /**
     * Recursive tree of PUBLISHED descendants for the public site
     * (only published pages — each node carries its own published children).
     */
    protected function publicChildrenTree(SmartPage $page): array
    {
        return $page->children()
            ->where('status', 'published')
            ->get()
            ->map(fn (SmartPage $child) => $this->formatChildrenRow($child) + [
                'children' => $this->publicChildrenTree($child),
            ])
            ->values()
            ->all();
    }
}
