<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormShareLink;
use App\Models\FormSubmission;
use App\Models\Language;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FormController extends Controller
{
    // ==================== ADMIN LIST / DETAIL ====================

    /**
     * Admin list of forms with search, filter, and pagination.
     * GET /forms?search=&status=&type=&page=&per_page=
     */
    public function index(Request $request): JsonResponse
    {
        $query = Form::query();

        // Filter by content language (default: default language)
        $query->where('language', \App\Models\Language::resolve($request->input('lang')));

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            // «status=published,page_builder_only» هم پشتیبانی می‌شود — مثلاً برای فهرست
            // فرم‌های قابل‌جاسازی در ویجت «فرم» صفحه‌ساز هوشمند
            $statuses = array_filter(explode(',', $request->input('status')));
            $query->whereIn('status', $statuses);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $query->orderBy('updated_at', 'desc');

        $perPage = min((int) $request->input('per_page', 15), 500);
        $forms = $query->paginate($perPage);

        return response()->json($forms);
    }

    /**
     * Admin single form detail (full definition).
     * GET /forms/{id}
     */
    public function show(int $id): JsonResponse
    {
        $form = Form::find($id);

        if (!$form) {
            return response()->json(['message' => 'فرم یافت نشد'], 404);
        }

        return response()->json(['data' => $form]);
    }

    // ==================== ADMIN CRUD ====================

    /**
     * Create a new form.
     * POST /forms
     */
    public function store(Request $request): JsonResponse
    {
        $lang = Language::resolveRequest($request);
        $validated = $this->validatePayload($request, null, false, $lang);

        $user = $request->user();

        // If user does NOT have approve permission, force status to draft (same as SmartPageController::store())
        if (!$user->can('forms.approve') && in_array($validated['status'] ?? 'draft', ['published', 'page_builder_only'], true)) {
            $validated['status'] = 'draft';
        }

        $form = Form::create([
            'language'       => $lang,
            'title'          => $validated['title'],
            'slug'           => $validated['slug'],
            'description'    => $validated['description'] ?? null,
            'type'           => $validated['type'] ?? 'form',
            'status'         => $validated['status'] ?? 'draft',
            'category'       => $validated['category'] ?? null,
            'owner_username' => $user->username,
            'version'        => 1,
            'tags'           => $validated['tags'] ?? [],
            'steps'          => $validated['steps'] ?? [],
            'fields'         => $validated['fields'] ?? [],
            'logic_rules'    => $validated['logic_rules'] ?? [],
            'quiz_config'    => $validated['quiz_config'] ?? null,
            'theme'          => $validated['theme'] ?? null,
            'settings'       => $validated['settings'] ?? null,
            'published_at'   => ($validated['status'] ?? 'draft') === 'published' ? now() : null,
        ]);

        return response()->json([
            'message' => 'فرم با موفقیت ایجاد شد',
            'data'    => $form->fresh(),
        ], 201);
    }

    /**
     * Update a form.
     * PUT /forms/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $form = Form::find($id);

        if (!$form) {
            return response()->json(['message' => 'فرم یافت نشد'], 404);
        }

        // Slug uniqueness is scoped per language — if the request is also
        // changing the form's language, validate against the NEW language.
        $targetLang = ($request->filled('lang') || $request->filled('language'))
            ? Language::resolveRequest($request)
            : $form->language;

        $validated = $this->validatePayload($request, $id, sometimes: true, targetLang: $targetLang);

        if ($request->filled('lang') || $request->filled('language')) {
            $validated['language'] = $targetLang;
        }

        // If user does NOT have approve permission, prevent publishing via a plain update
        if (isset($validated['status']) && in_array($validated['status'], ['published', 'page_builder_only'], true) && !$request->user()->can('forms.approve')) {
            $validated['status'] = 'draft';
        }

        if (isset($validated['status'])) {
            $validated['published_at'] = $validated['status'] === 'published' ? now() : $form->published_at;
        }

        if (array_key_exists('fields', $validated) || array_key_exists('steps', $validated) || array_key_exists('logic_rules', $validated)) {
            $validated['version'] = $form->version + 1;
        }

        $form->update($validated);

        return response()->json([
            'message' => 'فرم با موفقیت به‌روزرسانی شد',
            'data'    => $form->fresh(),
        ]);
    }

    /**
     * Flip a form's publish status.
     * PATCH /forms/{id}/status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $form = Form::find($id);

        if (!$form) {
            return response()->json(['message' => 'فرم یافت نشد'], 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:draft,published,paused,archived,page_builder_only',
        ]);

        if (in_array($validated['status'], ['published', 'page_builder_only'], true) && !$request->user()->can('forms.approve')) {
            return response()->json(['message' => 'شما اجازهٔ انتشار فرم را ندارید'], 403);
        }

        $form->update([
            'status'       => $validated['status'],
            'published_at' => $validated['status'] === 'published' ? ($form->published_at ?? now()) : $form->published_at,
        ]);

        return response()->json([
            'message' => 'وضعیت فرم به‌روزرسانی شد',
            'data'    => $form->fresh(),
        ]);
    }

    /**
     * Clone a form (new id/slug, always starts as draft).
     * POST /forms/{id}/clone
     */
    public function clone(Request $request, int $id): JsonResponse
    {
        $source = Form::find($id);

        if (!$source) {
            return response()->json(['message' => 'فرم یافت نشد'], 404);
        }

        $baseSlug = $source->slug . '-copy';
        $slug = $baseSlug;
        $i = 1;
        while (Form::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . (++$i);
        }

        $clone = Form::create([
            'language'       => $source->language,
            'title'          => $source->title . ' (کپی)',
            'slug'           => $slug,
            'description'    => $source->description,
            'type'           => $source->type,
            'status'         => 'draft',
            'category'       => $source->category,
            'owner_username' => $request->user()->username,
            'version'        => 1,
            'tags'           => $source->tags,
            'steps'          => $source->steps,
            'fields'         => $source->fields,
            'logic_rules'    => $source->logic_rules,
            'quiz_config'    => $source->quiz_config,
            'theme'          => $source->theme,
            'settings'       => $source->settings,
        ]);

        return response()->json([
            'message' => 'فرم با موفقیت کپی شد',
            'data'    => $clone,
        ], 201);
    }

    /**
     * Duplicate a form into another language — since each language variant
     * is an independent record (schema, steps, fields, everything is copied
     * so the admin has a working starting point to translate), this is the
     * supported way to "create the same form in another language" without
     * rebuilding it from scratch. Mirrors SmartPageController::duplicate().
     *
     * The copy is always saved as a draft. POST /forms/{id}/duplicate { lang: "en" }
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        $source = Form::find($id);

        if (!$source) {
            return response()->json(['message' => 'فرم یافت نشد'], 404);
        }

        $request->validate([
            'language' => 'nullable|string|max:10',
            'lang'     => 'nullable|string|max:10',
        ]);
        $targetLang = Language::resolveRequest($request);

        if ($targetLang === $source->language) {
            return response()->json(['message' => 'زبان مقصد باید با زبان فرم فعلی متفاوت باشد'], 422);
        }

        // Keep the source's slug if free in the target language, otherwise suffix it.
        $slug = $source->slug;
        if (Form::where('slug', $slug)->where('language', $targetLang)->exists()) {
            $slug = $slug . '-' . $targetLang;
            $suffixed = $slug;
            $n = 2;
            while (Form::where('slug', $suffixed)->where('language', $targetLang)->exists()) {
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

        $copy = Form::create([
            'language'          => $targetLang,
            'translation_group' => $group,
            'title'             => $source->title,
            'slug'              => $slug,
            'description'       => $source->description,
            'type'              => $source->type,
            'status'            => 'draft',
            'category'          => $source->category,
            'owner_username'    => $request->user()->username,
            'version'           => 1,
            'tags'              => $source->tags,
            'steps'             => $source->steps,
            'fields'            => $source->fields,
            'layout_blocks'     => $source->layout_blocks,
            'logic_rules'       => $source->logic_rules,
            'quiz_config'       => $source->quiz_config,
            'theme'             => $source->theme,
            'settings'          => $source->settings,
        ]);

        return response()->json([
            'message' => 'نسخهٔ فرم در زبان مقصد ایجاد شد — اکنون می‌توانید محتوای آن را ترجمه کنید',
            'data'    => $copy,
        ], 201);
    }

    // ==================== SHARE LINK (password/expiry protected form link) ====================

    /**
     * Get (or lazily create) this form's dedicated share link settings.
     * Never exposes the password itself — only whether one is set.
     * GET /forms/{id}/share-link
     */
    public function getShareLink(int $id): JsonResponse
    {
        $form = Form::find($id);

        if (!$form) {
            return response()->json(['message' => 'فرم یافت نشد'], 404);
        }

        $link = $form->shareLink ?? $form->shareLink()->create([
            'slug'      => $this->generateShareSlug($form),
            'is_active' => false,
        ]);

        return response()->json(['data' => $this->formatShareLink($link)]);
    }

    /**
     * Update this form's share link settings.
     * PUT /forms/{id}/share-link
     */
    public function updateShareLink(Request $request, int $id): JsonResponse
    {
        $form = Form::find($id);

        if (!$form) {
            return response()->json(['message' => 'فرم یافت نشد'], 404);
        }

        $link = $form->shareLink ?? $form->shareLink()->create([
            'slug'      => $this->generateShareSlug($form),
            'is_active' => false,
        ]);

        $validated = $request->validate([
            'slug'       => 'sometimes|required|string|max:191|regex:/^[a-z0-9\-]+$/|unique:form_share_links,slug,' . $link->id,
            'password'   => 'sometimes|nullable|string|min:4|max:100',
            'expires_at' => 'sometimes|nullable|date',
            'is_active'  => 'sometimes|boolean',
        ]);

        $link->update($validated);

        return response()->json(['data' => $this->formatShareLink($link->fresh())]);
    }

    /**
     * Delete a form and its submissions.
     * DELETE /forms/{id}
     *
     * NOTE: this database's default storage engine is MyISAM (confirmed project-wide,
     * not specific to this table), which does not enforce foreign keys at all — the
     * `onDelete('cascade')` declared in the migration is silently a no-op here, so
     * submissions and the share link must be deleted explicitly instead of relying on it.
     */
    public function destroy(int $id): JsonResponse
    {
        $form = Form::find($id);

        if (!$form) {
            return response()->json(['message' => 'فرم یافت نشد'], 404);
        }

        $submissionCount = $form->submissions()->count();
        $form->submissions()->delete();
        $form->shareLink()->delete();
        $form->delete();

        return response()->json([
            'message' => $submissionCount > 0
                ? "فرم و {$submissionCount} پاسخ ثبت‌شدهٔ آن حذف شد"
                : 'فرم با موفقیت حذف شد',
        ]);
    }

    // ==================== SUBMISSIONS (staff) ====================

    /**
     * Staff list of a form's submissions.
     * GET /forms/{id}/submissions?status=&page=&per_page=
     */
    public function submissions(Request $request, int $id): JsonResponse
    {
        $form = Form::find($id);

        if (!$form) {
            return response()->json(['message' => 'فرم یافت نشد'], 404);
        }

        $query = $form->submissions()->orderBy('submitted_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = min((int) $request->input('per_page', 20), 500);

        return response()->json($query->paginate($perPage));
    }

    // ==================== PUBLIC ENDPOINTS ====================

    /**
     * Public list of published forms (standalone-URL forms only, not page_builder_only)
     * — برای sitemap.ts سایت عمومی.
     * GET /forms/public
     */
    public function publicIndex(): JsonResponse
    {
        $forms = Form::published()
            ->orderBy('updated_at', 'desc')
            ->get(['id', 'slug', 'updated_at']);

        return response()->json($forms);
    }

    /**
     * Public single form by slug (published only) — for the public fill-out page.
     * GET /forms/slug/{slug}/public
     */
    public function publicShowBySlug(string $slug): JsonResponse
    {
        $form = Form::published()->where('slug', $slug)->first();

        if (!$form) {
            return response()->json(['message' => 'فرم یافت نشد یا منتشر نشده است'], 404);
        }

        $form->increment('views_count');

        return response()->json(['data' => $form]);
    }

    /**
     * Public single form by slug — برای جاسازی داخل ویجت «فرم» صفحه‌ساز هوشمند.
     * برخلاف publicShowBySlug، فرم‌های page_builder_only را هم برمی‌گرداند (چون این
     * وضعیت دقیقاً برای همین منظور است)؛ صفحهٔ مستقل /forms/{slug} همچنان فقط از
     * publicShowBySlug استفاده می‌کند و برای این وضعیت 404 می‌دهد.
     * GET /forms/slug/{slug}/embed
     */
    public function publicShowBySlugForEmbed(string $slug): JsonResponse
    {
        $form = Form::live()->where('slug', $slug)->first();

        if (!$form) {
            return response()->json(['message' => 'فرم یافت نشد یا در دسترس نیست'], 404);
        }

        $form->increment('views_count');

        return response()->json(['data' => $form]);
    }

    /**
     * Public lookup by a form's dedicated share-link slug (independent of the
     * form's own permanent slug). Gated by is_active/expires_at and, if set,
     * a password — in which case the form is NOT returned here.
     * GET /forms/share/{slug}/public
     */
    public function publicShowByShareSlug(string $slug): JsonResponse
    {
        $link = FormShareLink::where('slug', $slug)->first();

        if (!$link || !$link->isValid()) {
            return response()->json(['message' => 'لینک یافت نشد یا منقضی شده است'], 404);
        }

        if ($link->hasPassword()) {
            return response()->json([
                'message' => 'این فرم با رمز عبور محافظت شده است',
                'requires_password' => true,
            ], 423);
        }

        $form = $link->form;
        $form->increment('views_count');

        return response()->json(['data' => $form]);
    }

    /**
     * Verify a share link's password and, on success, return the form —
     * mirrors publicShowByShareSlug's response shape for a password-protected link.
     * POST /forms/share/{slug}/unlock
     */
    public function unlockShareLink(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'required|string',
        ]);

        $link = FormShareLink::where('slug', $slug)->first();

        if (!$link || !$link->isValid()) {
            return response()->json(['message' => 'لینک یافت نشد یا منقضی شده است'], 404);
        }

        if (!$link->verifyPassword($validated['password'])) {
            return response()->json(['message' => 'رمز عبور نادرست است'], 401);
        }

        $form = $link->form;
        $form->increment('views_count');

        return response()->json(['data' => $form]);
    }

    /**
     * Public submission of a form's answers. Computes the quiz score/grade
     * SERVER-SIDE (never trusts a client-submitted score) and generates the
     * tracking code server-side.
     * POST /forms/{id}/submit
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        $form = Form::live()->find($id);

        if (!$form) {
            return response()->json(['message' => 'فرم یافت نشد یا منتشر نشده است'], 404);
        }

        $validated = $request->validate([
            'answers'                 => 'required|array',
            'security_challenges'     => 'sometimes|array',
            'respondent_name'         => 'nullable|string|max:200',
            'respondent_email'        => 'nullable|email|max:191',
            'respondent_role'         => 'nullable|string|max:100',
            'completion_time_seconds' => 'nullable|integer|min:0',
        ]);

        $securityErrors = $this->validateSecurityFields($form, $validated['security_challenges'] ?? []);
        if (!empty($securityErrors)) {
            return response()->json([
                'message' => 'تایید امنیتی ناموفق بود. لطفاً دوباره تلاش کنید.',
                'errors'  => $securityErrors,
            ], 422);
        }

        // پاسخ فیلدهای امنیتی (توکن/مقدار کپچا، مقدار تلهٔ ضدربات) صرفاً یک سازوکار
        // ضدربات هستند که همین بالا مصرف/بررسی شدند — هیچ دادهٔ معناداری از پاسخ‌دهنده
        // ندارند، پس در پاسخ‌های ذخیره‌شده نگه‌داشته نمی‌شوند (حتی اگر کلاینتی هنوز
        // آن‌ها را داخل answers هم بفرستد، این فیلتر دفاعی حذفشان می‌کند)
        $persistedAnswers = $this->stripSecurityFieldAnswers($form, $validated['answers']);

        [$scoreTotal, $gradeLabel] = $this->scoreSubmission($form, $persistedAnswers);

        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $form->settings['trackingCodePrefix'] ?? '') ?: 'FRM');
        do {
            $trackingCode = $prefix . '-' . strtoupper(Str::random(8));
        } while (FormSubmission::where('tracking_code', $trackingCode)->exists());

        $submission = FormSubmission::create([
            'form_id'                 => $form->id,
            'tracking_code'           => $trackingCode,
            'respondent_name'         => $validated['respondent_name'] ?? null,
            'respondent_email'        => $validated['respondent_email'] ?? null,
            'respondent_role'         => $validated['respondent_role'] ?? null,
            'status'                  => 'new',
            'answers'                 => $persistedAnswers,
            'score_total'             => $scoreTotal,
            'grade_label'             => $gradeLabel,
            'ip_address'              => $request->ip(),
            'user_agent'              => $request->userAgent(),
            'completion_time_seconds' => $validated['completion_time_seconds'] ?? null,
            'submitted_at'            => now(),
        ]);

        $form->increment('submissions_count');

        return response()->json([
            'message' => 'پاسخ شما با موفقیت ثبت شد',
            'data'    => [
                'tracking_code' => $submission->tracking_code,
                'score_total'   => $submission->score_total,
                'grade_label'   => $submission->grade_label,
            ],
        ], 201);
    }

    /**
     * Public, unauthenticated upload for a file/image/signature answer field.
     * Strict mime/size allowlist and per-IP rate limiting (route middleware) —
     * no precedent for anonymous uploads exists elsewhere in this codebase, so
     * these controls are intentionally tighter than the staff media uploader.
     * POST /forms/{id}/upload-answer-file
     */
    public function uploadAnswerFile(Request $request, int $id): JsonResponse
    {
        $form = Form::live()->find($id);

        if (!$form) {
            return response()->json(['message' => 'فرم یافت نشد یا منتشر نشده است'], 404);
        }

        $request->validate([
            'file' => 'required|file|max:5120|mimes:jpg,jpeg,png,pdf',
        ]);

        $file = $request->file('file');
        $directory = "form-uploads/{$form->id}";
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

        if (!Storage::disk('public')->putFileAs($directory, $file, $filename)) {
            return response()->json(['message' => 'خطا در ذخیره‌سازی فایل'], 500);
        }

        return response()->json([
            'data' => [
                'url' => Storage::disk('public')->url("{$directory}/{$filename}"),
            ],
        ], 201);
    }

    // ==================== HELPERS ====================

    /**
     * Generate a unique default slug for a new share link, based on the
     * form's own slug — same collision-loop style as clone().
     */
    private function generateShareSlug(Form $form): string
    {
        $baseSlug = $form->slug . '-share';
        $slug = $baseSlug;
        $i = 1;
        while (FormShareLink::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . (++$i);
        }

        return $slug;
    }

    /**
     * Format a share link for the admin API — never exposes the password/hash.
     */
    private function formatShareLink(FormShareLink $link): array
    {
        return [
            'slug'         => $link->slug,
            'has_password' => $link->hasPassword(),
            'expires_at'   => $link->expires_at,
            'is_active'    => $link->is_active,
        ];
    }

    /**
     * Shared create/update validation — JSON-blob fields are validated only
     * shallowly (`array`), matching this codebase's SmartPage/DedicatedPage
     * convention of opaque JSON storage with no nested schema validation.
     */
    private function validatePayload(Request $request, ?int $ignoreId = null, bool $sometimes = false, ?string $targetLang = null): array
    {
        $req = $sometimes ? 'sometimes|required' : 'required';
        $targetLang ??= Language::resolveRequest($request);
        $slugUnique = Rule::unique('forms', 'slug')
            ->where(fn ($q) => $q->where('language', $targetLang))
            ->ignore($ignoreId);

        return $request->validate([
            'title'       => "{$req}|string|max:300",
            'slug'        => ["{$req}", 'string', 'max:191', 'regex:/^[a-z0-9\-]+$/', $slugUnique],
            'description' => 'nullable|string',
            'type'        => 'sometimes|required|in:form,survey,quiz,registration',
            'status'      => 'sometimes|required|in:draft,published,paused,archived,page_builder_only',
            'category'    => 'nullable|string|max:150',
            'tags'        => 'sometimes|array',
            'steps'       => 'sometimes|array',
            'fields'      => 'sometimes|array',
            'layout_blocks' => 'sometimes|array',
            'logic_rules' => 'sometimes|array',
            'quiz_config' => 'sometimes|nullable|array',
            'theme'       => 'sometimes|nullable|array',
            'settings'    => 'sometimes|nullable|array',
        ]);
    }

    /**
     * Server-side quiz scoring — walks the form's own `fields` definition
     * (never the client-submitted score) comparing each field with a
     * `correctAnswer` against the respondent's answer for that field id.
     * Returns [scoreTotal, gradeLabel] — both null when the form isn't a quiz.
     */
    private function scoreSubmission(Form $form, array $answers): array
    {
        $quizConfig = $form->quiz_config;
        if (empty($quizConfig['isQuiz'])) {
            return [null, null];
        }

        $scoreTotal = 0;
        foreach (($form->fields ?? []) as $field) {
            if (!array_key_exists('correctAnswer', $field) || $field['correctAnswer'] === null) {
                continue;
            }

            $fieldId = $field['id'] ?? null;
            $submitted = $answers[$fieldId] ?? null;
            $correct = $field['correctAnswer'];
            $points = (int) ($field['points'] ?? 0);

            $isCorrect = is_array($correct)
                ? (is_array($submitted) && empty(array_diff($correct, $submitted)) && empty(array_diff($submitted, $correct)))
                : (is_array($submitted) ? false : (string) $submitted === (string) $correct);

            if ($isCorrect) {
                $scoreTotal += $points;
            } elseif (!empty($quizConfig['allowNegativeScore'])) {
                $scoreTotal -= $points;
            }
        }

        $gradeLabel = null;
        foreach (($quizConfig['gradeThresholds'] ?? []) as $threshold) {
            if ($scoreTotal >= ($threshold['minScore'] ?? PHP_INT_MIN) && $scoreTotal <= ($threshold['maxScore'] ?? PHP_INT_MAX)) {
                $gradeLabel = $threshold['gradeLabel'] ?? null;
                break;
            }
        }

        return [$scoreTotal, $gradeLabel];
    }

    /**
     * Authoritative server-side check for every «فیلد امنیتی» (security field,
     * type === 'security') in the form — never trusts SecurityChallengeController's
     * verify() alone, since that endpoint only gives the client instant feedback
     * and never consumes the token. `$challenges` is the request's dedicated
     * `security_challenges` map (fieldId => value) — kept entirely separate from
     * `answers` so this anti-bot bookkeeping never ends up in a stored submission
     * (see stripSecurityFieldAnswers()). For CAPTCHA-family appearances the
     * submitted value must be `{token, value}`; the matching cache entry (created
     * by SecurityChallengeController::generate) is re-checked and consumed here
     * (single-use). For the honeypot appearance, the field must arrive empty —
     * a filled honeypot means an automated submission.
     * Returns a Laravel-style [fieldId => [message]] error map, empty when all pass.
     */
    private function validateSecurityFields(Form $form, array $challenges): array
    {
        $errors = [];

        foreach (($form->fields ?? []) as $field) {
            if (($field['type'] ?? null) !== 'security') {
                continue;
            }

            $fieldId = $field['id'] ?? null;
            if (!$fieldId) {
                continue;
            }

            $challengeAnswer = $challenges[$fieldId] ?? null;
            $securityType = $field['securityType'] ?? 'image_captcha';

            if ($securityType === 'honeypot') {
                if (!empty($challengeAnswer)) {
                    $errors[$fieldId] = ['درخواست شما به‌عنوان فعالیت خودکار (ربات) شناسایی شد.'];
                }
                continue;
            }

            $required = $field['validation']['required'] ?? true;
            if (!$required && empty($challengeAnswer)) {
                continue;
            }

            $token = is_array($challengeAnswer) ? ($challengeAnswer['token'] ?? null) : null;
            $value = is_array($challengeAnswer) ? ($challengeAnswer['value'] ?? null) : null;

            if (!$token || $value === null || $value === '') {
                $errors[$fieldId] = ['تکمیل کد امنیتی الزامی است.'];
                continue;
            }

            $key = 'security_challenge_' . $token;
            $challenge = Cache::get($key);

            if (!$challenge) {
                $errors[$fieldId] = ['کد امنیتی منقضی شده است. لطفاً کد جدید دریافت کنید.'];
                continue;
            }

            $submitted = $challenge['case_sensitive'] ? (string) $value : mb_strtolower((string) $value);

            if (!hash_equals($challenge['answer'], $submitted)) {
                Cache::forget($key);
                $errors[$fieldId] = ['کد امنیتی واردشده نادرست است.'];
                continue;
            }

            Cache::forget($key);
        }

        return $errors;
    }

    /**
     * Drops every «فیلد امنیتی» (security field) entry from a respondent's answers
     * before they're persisted — its CAPTCHA token/value or honeypot trap value is
     * anti-bot bookkeeping, already consumed by validateSecurityFields() above, and
     * carries no meaningful respondent data worth keeping on the submission record.
     */
    private function stripSecurityFieldAnswers(Form $form, array $answers): array
    {
        $securityFieldIds = [];
        foreach (($form->fields ?? []) as $field) {
            if (($field['type'] ?? null) === 'security' && !empty($field['id'])) {
                $securityFieldIds[] = $field['id'];
            }
        }

        return array_diff_key($answers, array_flip($securityFieldIds));
    }
}
