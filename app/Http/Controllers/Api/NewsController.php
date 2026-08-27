<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\NormalizesMediaUrls;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\RoleCategoryPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NewsController extends Controller
{
    use NormalizesMediaUrls;

    // ==================== NEWS CRUD ====================

    /**
     * List news with search, filter, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = News::query()->with('category')->withCount('approvedComments');

        // Filter by language (default: default language)
        $query->where('language', \App\Models\Language::resolve($request->input('lang')));

        // Search by title, summary, tags
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('summary', 'like', "%{$search}%")
                  ->orWhereRaw('JSON_CONTAINS(tags, ?)', ['"' . $search . '"']);
            });
        }

        // Filter by category_id (matches primary category OR any of category_ids)
        if ($request->filled('category_id')) {
            $categoryId = (int) $request->input('category_id');
            $query->where(function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId)
                  ->orWhereJsonContains('category_ids', $categoryId);
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        } elseif (!$request->user()) {
            // Public/unauthenticated requests: only show published news
            $query->where('status', 'published');
        }

        // Filter by target_audience
        if ($request->filled('target_audience')) {
            $query->where('target_audience', $request->input('target_audience'));
        }

        // Filter by category-level permissions (if user has restrictions)
        $this->applyCategoryRestriction($request, $query, 'news', 'view');

        // Pinned first, then sort
        $sortBy = $request->input('sort', 'newest');
        $query->orderBy('is_pinned', 'desc');

        match ($sortBy) {
            'views' => $query->orderBy('views_count', 'desc'),
            'likes' => $query->orderBy('likes_count', 'desc'),
            default => $query->orderBy('id', 'desc'),
        };

        $perPage = min((int) $request->input('per_page', 15), 500);
        $news = $query->paginate($perPage);

        $news->getCollection()->transform(function ($item) {
            return $this->formatNews($item);
        });

        return response()->json($news);
    }

    /**
     * Get a single news article with full details.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $news = News::with('category')->with('approvedComments')->withCount('approvedComments')->find($id);

        if (!$news) {
            return response()->json(['message' => 'خبر یافت نشد'], 404);
        }

        // Public users cannot view non-published news
        if (!$request->user() && $news->status !== 'published') {
            return response()->json(['message' => 'خبر یافت نشد'], 404);
        }

        return response()->json([
            'data' => $this->formatNewsDetailed($news),
        ]);
    }

    /**
     * Create a new news article.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:300',
            'summary' => 'nullable|string',
            'content' => 'required_if:is_photo_report,false|nullable|string',
            'language' => 'nullable|string|max:10',
            'lang' => 'nullable|string|max:10',
            'category_id' => 'nullable|integer|exists:news_categories,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:news_categories,id',
            'image_url' => 'nullable|string|max:1000',
            'status' => 'required|in:published,draft,archived',
            'target_audience' => 'nullable|in:all,students,professors,staff',
            'is_pinned' => 'boolean',
            'comments_enabled' => 'boolean',
            'comments_mode' => 'nullable|in:auto,approval,disabled',
            'is_photo_report' => 'boolean',
            'photo_report_images' => 'nullable|array',
            'photo_report_images.*.url' => 'required_with:photo_report_images|string|max:1000',
            'photo_report_images.*.title' => 'nullable|string|max:500',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'attachments' => 'nullable|array',
            'published_at' => 'nullable|date',
        ]);

        // Validate category access (only when a primary category is set)
        if (($validated['category_id'] ?? null) !== null) {
            if (!$this->canAccessCategory($request, 'news', 'create', (int) $validated['category_id'])) {
                return response()->json([
                    'message' => 'شما دسترسی ایجاد خبر در این دسته‌بندی را ندارید',
                ], 403);
            }
        }

        $user = $request->user();

        // If user does NOT have approve permission, force status to draft
        if (!$user->can('news.approve') && $validated['status'] === 'published') {
            $validated['status'] = 'draft';
        }

        $categoryIds = $this->normalizeCategoryIds($validated);
        $primaryCategoryId = $validated['category_id'] ?? null;
        if ($primaryCategoryId === null && !empty($categoryIds)) {
            $primaryCategoryId = $categoryIds[0];
        }

        $news = News::create([
            'language' => \App\Models\Language::resolveRequest($request),
            'title' => $validated['title'],
            'summary' => $validated['summary'] ?? null,
            'content' => $validated['content'] ?? '',
            'category_id' => $primaryCategoryId,
            'category_ids' => $categoryIds,
            'author_username' => $user->username,
            'author_name' => trim(($user->fname ?? '') . ' ' . ($user->lname ?? '')),
            'author_role' => $user->role ?? null,
            'image_url' => $this->normalizeMediaUrlValue($validated['image_url'] ?? null),
            'is_pinned' => $validated['is_pinned'] ?? false,
            'comments_enabled' => $this->resolveCommentsEnabled($validated),
            'comments_mode' => $validated['comments_mode'] ?? 'approval',
            'is_photo_report' => $validated['is_photo_report'] ?? false,
            'photo_report_images' => $this->normalizePhotoReportImages($validated['photo_report_images'] ?? []),
            'status' => $validated['status'],
            'target_audience' => $validated['target_audience'] ?? 'all',
            'tags' => $validated['tags'] ?? [],
            'attachments' => $this->normalizeAttachments($validated['attachments'] ?? []),
            'published_at' => $validated['status'] === 'published'
                ? ($validated['published_at'] ?? now())
                : null,
        ]);

        return response()->json([
            'message' => 'خبر با موفقیت ایجاد شد',
            'data' => $this->formatNewsDetailed($news->load('category')),
        ], 201);
    }

    /**
     * Update a news article.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $news = News::find($id);

        if (!$news) {
            return response()->json(['message' => 'خبر یافت نشد'], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:300',
            'summary' => 'nullable|string',
            'content' => 'nullable|string',
            'language' => 'nullable|string|max:10',
            'lang' => 'nullable|string|max:10',
            'category_id' => 'sometimes|nullable|integer|exists:news_categories,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:news_categories,id',
            'image_url' => 'nullable|string|max:1000',
            'status' => 'sometimes|required|in:published,draft,archived',
            'target_audience' => 'nullable|in:all,students,professors,staff',
            'is_pinned' => 'boolean',
            'comments_enabled' => 'boolean',
            'comments_mode' => 'nullable|in:auto,approval,disabled',
            'is_photo_report' => 'boolean',
            'photo_report_images' => 'nullable|array',
            'photo_report_images.*.url' => 'required_with:photo_report_images|string|max:1000',
            'photo_report_images.*.title' => 'nullable|string|max:500',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'attachments' => 'nullable|array',
            'published_at' => 'nullable|date',
        ]);

        // Resolve language from lang/language if provided, else keep existing
        if ($request->filled('lang') || $request->filled('language')) {
            $validated['language'] = \App\Models\Language::resolveRequest($request);
        }

        // Validate category access if changing category
        if (array_key_exists('category_id', $validated)) {
            if ($validated['category_id'] !== null) {
                if (!$this->canAccessCategory($request, 'news', 'edit', (int) $validated['category_id'])) {
                    return response()->json([
                        'message' => 'شما دسترسی ویرایش خبر در این دسته‌بندی را ندارید',
                    ], 403);
                }
            }
        }

        // If status changed to published and no published_at, set it now
        if (isset($validated['status']) && $validated['status'] === 'published' && !$news->published_at) {
            $validated['published_at'] = $validated['published_at'] ?? now();
        }

        // If user does NOT have approve permission, prevent publishing
        if (isset($validated['status']) && $validated['status'] === 'published') {
            if (!$request->user()->can('news.approve')) {
                $validated['status'] = 'draft';
            }
        }

        if (array_key_exists('image_url', $validated)) {
            $validated['image_url'] = $this->normalizeMediaUrlValue($validated['image_url'] ?? null);
        }

        if (array_key_exists('photo_report_images', $validated)) {
            $validated['photo_report_images'] = $this->normalizePhotoReportImages($validated['photo_report_images'] ?? []);
        }

        if (array_key_exists('attachments', $validated)) {
            $validated['attachments'] = $this->normalizeAttachments($validated['attachments'] ?? []);
        }

        // Normalize multi-category: sync category_ids, keep category_id in sync
        if (array_key_exists('category_ids', $validated) || array_key_exists('category_id', $validated)) {
            $categoryIds = $this->normalizeCategoryIds($validated);
            $validated['category_ids'] = $categoryIds;
            if (array_key_exists('category_id', $validated) && $validated['category_id'] === null && !empty($categoryIds)) {
                $validated['category_id'] = $categoryIds[0];
            }
            if (!array_key_exists('category_id', $validated)) {
                $validated['category_id'] = !empty($categoryIds) ? $categoryIds[0] : $news->category_id;
            }
        }

        // Normalize comments mode -> comments_enabled compatibility
        if (array_key_exists('comments_mode', $validated)) {
            $validated['comments_enabled'] = $validated['comments_mode'] !== 'disabled';
        }

        $news->update($validated);

        return response()->json([
            'message' => 'خبر با موفقیت به‌روزرسانی شد',
            'data' => $this->formatNewsDetailed($news->fresh()->load('category')),
        ]);
    }

    /**
     * Delete a news article.
     */
    public function destroy(int $id): JsonResponse
    {
        $news = News::find($id);

        if (!$news) {
            return response()->json(['message' => 'خبر یافت نشد'], 404);
        }

        $news->delete();

        return response()->json([
            'message' => 'خبر با موفقیت حذف شد',
        ]);
    }

    // ==================== NEWS ACTIONS ====================

    /**
     * Toggle pinned status of a news article.
     */
    public function togglePin(int $id): JsonResponse
    {
        $news = News::find($id);

        if (!$news) {
            return response()->json(['message' => 'خبر یافت نشد'], 404);
        }

        $news->update(['is_pinned' => !$news->is_pinned]);

        return response()->json([
            'message' => $news->is_pinned ? 'خبر به اخبار ویژه اضافه شد' : 'خبر از اخبار ویژه حذف شد',
            'data' => ['is_pinned' => $news->is_pinned],
        ]);
    }

    /**
     * Like a news article (increment likes count).
     */
    public function like(int $id): JsonResponse
    {
        $news = News::find($id);

        if (!$news) {
            return response()->json(['message' => 'خبر یافت نشد'], 404);
        }

        $news->increment('likes_count');

        return response()->json([
            'data' => ['likes_count' => $news->fresh()->likes_count],
        ]);
    }

    /**
     * Increment views count for a news article.
     */
    public function incrementViews(int $id): JsonResponse
    {
        $news = News::find($id);

        if (!$news) {
            return response()->json(['message' => 'خبر یافت نشد'], 404);
        }

        $news->increment('views_count');

        return response()->json([
            'data' => ['views_count' => $news->fresh()->views_count],
        ]);
    }

    // ==================== CATEGORIES ====================

    /**
     * List all categories with news count.
     */
    public function categories(Request $request): JsonResponse
    {
        $query = NewsCategory::orderBy('ordering')
            ->orderBy('name')
            ->where('language', \App\Models\Language::resolve($request->input('lang')));

        // Filter by category-level permissions (if user has restrictions)
        $this->applyCategoryRestriction($request, $query, 'news', 'view', 'id');

        $categories = $query->get()
            ->map(function ($cat) {
                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'color' => $cat->color,
                    'description' => $cat->description,
                    'is_active' => $cat->is_active,
                    'count' => News::where('category_id', $cat->id)->count(),
                ];
            });

        return response()->json(['data' => $categories]);
    }

    /**
     * Create a new category.
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'slug' => 'nullable|string|max:150|unique:news_categories,slug',
            'color' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'lang' => 'nullable|string|max:10',
        ]);

        $slug = $validated['slug'] ?? Str::slug($validated['name']);
        $validated['slug'] = $slug !== '' ? $slug : ('cat-' . Str::lower(Str::random(8)));
        $validated['language'] = \App\Models\Language::resolveRequest($request);

        $category = NewsCategory::create($validated);

        return response()->json([
            'message' => 'دسته‌بندی با موفقیت ایجاد شد',
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'color' => $category->color,
                'description' => $category->description,
                'count' => 0,
            ],
        ], 201);
    }

    /**
     * Update a category.
     */
    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $category = NewsCategory::find($id);

        if (!$category) {
            return response()->json(['message' => 'دسته‌بندی یافت نشد'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:150',
            'slug' => 'nullable|string|max:150|unique:news_categories,slug,' . $id,
            'color' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $category->update($validated);

        return response()->json([
            'message' => 'دسته‌بندی با موفقیت به‌روزرسانی شد',
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'color' => $category->color,
                'description' => $category->description,
                'is_active' => $category->is_active,
                'count' => News::where('category_id', $category->id)->count(),
            ],
        ]);
    }

    /**
     * Delete a category.
     */
    public function destroyCategory(int $id): JsonResponse
    {
        $category = NewsCategory::find($id);

        if (!$category) {
            return response()->json(['message' => 'دسته‌بندی یافت نشد'], 404);
        }

        // Move news in this category to null (no category)
        News::where('category_id', $id)->update(['category_id' => null]);

        $category->delete();

        return response()->json([
            'message' => 'دسته‌بندی با موفقیت حذف شد',
        ]);
    }

    // ==================== ANALYTICS ====================

    /**
     * Get news analytics data.
     */
    public function analytics(): JsonResponse
    {
        $totalNews = News::count();
        $publishedNews = News::where('status', 'published')->count();
        $draftNews = News::where('status', 'draft')->count();
        $archivedNews = News::where('status', 'archived')->count();
        $pinnedNews = News::where('is_pinned', true)->count();
        $totalViews = (int) News::sum('views_count');
        $totalLikes = (int) News::sum('likes_count');

        // Top 10 most viewed
        $topViewed = News::orderBy('views_count', 'desc')
            ->limit(10)
            ->get(['id', 'title', 'category_id', 'views_count', 'likes_count']);

        // Top 10 most liked
        $topLiked = News::orderBy('likes_count', 'desc')
            ->limit(10)
            ->get(['id', 'title', 'category_id', 'views_count', 'likes_count']);

        // Category distribution
        $categories = NewsCategory::get(['id', 'name', 'color']);
        $categoryDistribution = $categories->map(function ($cat) use ($totalNews) {
            $count = News::where('category_id', $cat->id)->count();
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'color' => $cat->color,
                'count' => $count,
                'percentage' => $totalNews > 0 ? round(($count / $totalNews) * 100) : 0,
            ];
        });

        // Uncategorized count
        $uncategorizedCount = News::whereNull('category_id')->count();

        return response()->json([
            'data' => [
                'total_news' => $totalNews,
                'published_news' => $publishedNews,
                'draft_news' => $draftNews,
                'archived_news' => $archivedNews,
                'pinned_news' => $pinnedNews,
                'total_views' => $totalViews,
                'total_likes' => $totalLikes,
                'top_viewed' => $topViewed,
                'top_liked' => $topLiked,
                'category_distribution' => $categoryDistribution,
                'uncategorized_count' => $uncategorizedCount,
            ],
        ]);
    }

    // ==================== CATEGORY PERMISSION HELPERS ====================

    /**
     * Apply category-level access restriction to a query builder.
     *
     * If the user's roles have category restrictions for the given $categoryType,
     * the query will be filtered to only include matching categories.
     * If the user has no restrictions, all items are returned (no filter applied).
     *
     * @param  Request      $request
     * @param  mixed        $query    QueryBuilder instance
     * @param  string       $categoryType  e.g. 'news'
     * @param  string       $permission    e.g. 'view', 'create', 'edit'
     * @param  string       $column   The column name to filter on (e.g. 'category_id' for News, 'id' for NewsCategory)
     */
    private function applyCategoryRestriction(Request $request, $query, string $categoryType, string $permission, string $column = 'category_id'): void
    {
        $user = $request->user();
        if (!$user) return;

        // Super users (admin/support usernames) and admin-role users bypass all category restrictions
        if ($user->isSuperUser()) return;

        // Get all role IDs for this user
        $roleIds = $user->roles()->pluck('spatie_roles.id');

        if ($roleIds->isEmpty()) return;

        // Get category IDs that this user has explicit permission for
        $allowedCategoryIds = RoleCategoryPermission::whereIn('role_id', $roleIds)
            ->where('category_type', $categoryType)
            ->where('permission', $permission)
            ->pluck('category_id')
            ->unique()
            ->values();

        // If the user has ANY category restrictions, apply the filter
        // If no restrictions at all, show everything (backward compatible)
        if ($allowedCategoryIds->isNotEmpty()) {
            $query->whereIn($column, $allowedCategoryIds);
        }
    }

    /**
     * Check if the authenticated user can access a specific category.
     * Returns true if no restrictions exist, or if the category is in the allowed list.
     */
    private function canAccessCategory(Request $request, string $categoryType, string $permission, int $categoryId): bool
    {
        $user = $request->user();
        if (!$user) return false;

        // Super users (admin/support usernames) and admin-role users bypass all category restrictions
        if ($user->isSuperUser()) return true;

        $roleIds = $user->roles()->pluck('spatie_roles.id');
        if ($roleIds->isEmpty()) return false;

        $restrictedCategories = RoleCategoryPermission::whereIn('role_id', $roleIds)
            ->where('category_type', $categoryType)
            ->where('permission', $permission)
            ->pluck('category_id')
            ->unique()
            ->values();

        // If no restrictions, allow
        if ($restrictedCategories->isEmpty()) return true;

        // Check if the category is in the allowed list
        return $restrictedCategories->contains($categoryId);
    }

    // ==================== CATEGORY / COMMENTS MODE HELPERS ====================

    /**
     * Normalize the effective category ID list from validated input.
     * Prefers category_ids array, falls back to category_id, defaults to empty.
     */
    private function normalizeCategoryIds(array $validated): array
    {
        $ids = [];
        if (isset($validated['category_ids']) && is_array($validated['category_ids'])) {
            foreach ($validated['category_ids'] as $id) {
                if (is_numeric($id)) {
                    $ids[] = (int) $id;
                }
            }
        } elseif (isset($validated['category_id']) && $validated['category_id'] !== null) {
            $ids[] = (int) $validated['category_id'];
        }

        return array_values(array_unique($ids));
    }

    /**
     * Resolve comments_enabled boolean from comments_mode (and legacy flag).
     */
    private function resolveCommentsEnabled(array $validated): bool
    {
        if (isset($validated['comments_mode'])) {
            return $validated['comments_mode'] !== 'disabled';
        }

        return $validated['comments_enabled'] ?? true;
    }

    /**
     * Get the effective category IDs for a news record (JSON + legacy column).
     */
    private function effectiveCategoryIds(News $news): array
    {
        $ids = is_array($news->category_ids) ? $news->category_ids : [];
        $ids = array_map('intval', $ids);

        if ($news->category_id !== null) {
            $primary = (int) $news->category_id;
            if (!in_array($primary, $ids, true)) {
                array_unshift($ids, $primary);
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Resolve category names for a list of category IDs (cached per request).
     */
    private array $categoryNameCache = [];

    private function categoryNames(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return [];
        }

        $missing = array_diff($ids, array_keys($this->categoryNameCache));
        if (!empty($missing)) {
            $rows = \App\Models\NewsCategory::whereIn('id', $missing)->pluck('name', 'id');
            foreach ($rows as $id => $name) {
                $this->categoryNameCache[(int) $id] = $name;
            }
        }

        return array_map(fn($id) => $this->categoryNameCache[$id] ?? null, $ids);
    }

    // ==================== FORMATTERS ====================

    public function formatNews(News $news): array
    {
        $categoryIds = $this->effectiveCategoryIds($news);

        return [
            'id' => $news->id,
            'language' => $news->language,
            'title' => $news->title,
            'summary' => $news->summary,
            'category_id' => $news->category_id !== null ? (int) $news->category_id : null,
            'category_ids' => $categoryIds,
            'category_name' => $news->category?->name,
            'category_names' => $this->categoryNames($categoryIds),
            'category_color' => $news->category?->color,
            'author_username' => $news->author_username,
            'author_name' => $news->author_name,
            'image_url' => $this->resolveMediaUrlValue($news->image_url),
            'views_count' => (int) ($news->views_count ?? 0),
            'likes_count' => (int) ($news->likes_count ?? 0),
            'is_pinned' => $news->is_pinned,
            'comments_enabled' => $news->comments_enabled ?? false,
            'comments_mode' => $news->comments_mode ?? 'approval',
            'is_photo_report' => $news->is_photo_report ?? false,
            'comments_count' => (int) ($news->approved_comments_count ?? 0),
            'status' => $news->status,
            'target_audience' => $news->target_audience,
            'tags' => $news->tags ?? [],
            'published_at' => $news->published_at?->toISOString(),
            'created_at' => $news->created_at?->toISOString(),
            'updated_at' => $news->updated_at?->toISOString(),
        ];
    }

    private function formatNewsDetailed(News $news): array
    {
        $base = $this->formatNews($news) + [
            'content' => $news->content,
            'attachments' => $this->resolveAttachments($news->attachments ?? []),
            'photo_report_images' => $this->resolvePhotoReportImages($news->photo_report_images ?? []),
        ];

        // Include approved comments if the relation is loaded
        if ($news->relationLoaded('approvedComments')) {
            $base['comments'] = $news->approvedComments->map(fn($c) => [
                'id' => $c->id,
                'author_name' => $c->author_name,
                'content' => $c->content,
                'created_at' => $c->created_at?->toISOString(),
            ])->toArray();
        }

        return $base;
    }
}
