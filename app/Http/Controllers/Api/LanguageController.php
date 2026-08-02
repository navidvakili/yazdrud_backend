<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Services\TsLocaleParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LanguageController extends Controller
{
    /**
     * Absolute path to the public site's src/locales directory.
     */
    protected function localesPath(): string
    {
        return rtrim((string) config('services.public_locales_path', base_path('../public/src/locales')), '/\\');
    }

    /**
     * Full path of a language's locale TS file in the public site.
     */
    protected function localeFilePath(string $code): string
    {
        return $this->localesPath() . DIRECTORY_SEPARATOR . $code . '.ts';
    }
    /**
     * Public: List active languages (used by the public site header/settings).
     * GET /languages
     */
    public function publicIndex(): JsonResponse
    {
        $languages = Language::active()
            ->orderBy('ordering')
            ->get(['code', 'name', 'name_en', 'dir', 'is_default', 'ordering']);

        return response()->json([
            'data' => $languages,
        ]);
    }

    /**
     * Admin: List all languages (active + inactive).
     * GET /languages
     */
    public function index(): JsonResponse
    {
        $languages = Language::orderBy('ordering')->get();

        return response()->json([
            'data' => $languages,
        ]);
    }

    /**
     * Admin: Create a new language.
     * POST /languages
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:10|unique:languages,code',
            'name' => 'required|string|max:100',
            'name_en' => 'nullable|string|max:100',
            'dir' => 'required|in:rtl,ltr',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'ordering' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only(['code', 'name', 'name_en', 'dir', 'is_active', 'is_default', 'ordering']);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_default'] = $request->boolean('is_default', false);

        // Only one language can be default
        if ($data['is_default']) {
            Language::query()->update(['is_default' => false]);
        }

        $language = Language::create($data);

        // Create the public site locale file (<code>.ts) from the Persian template
        // so the new language's strings can be edited later.
        $this->createLocaleFile($language->code);

        return response()->json([
            'message' => 'زبان با موفقیت ایجاد شد.',
            'data' => $language,
        ], 201);
    }

    /**
     * Admin: Update a language.
     * PUT /languages/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $language = Language::find($id);

        if (!$language) {
            return response()->json(['message' => 'زبان یافت نشد'], 404);
        }

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:10|unique:languages,code,' . $id,
            'name' => 'required|string|max:100',
            'name_en' => 'nullable|string|max:100',
            'dir' => 'required|in:rtl,ltr',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'ordering' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only(['code', 'name', 'name_en', 'dir', 'is_active', 'is_default', 'ordering']);
        $data['is_active'] = $request->boolean('is_active', $language->is_active);
        $data['is_default'] = $request->boolean('is_default', $language->is_default);

        // Only one language can be default
        if ($data['is_default']) {
            Language::where('id', '!=', $id)->update(['is_default' => false]);
        }

        $oldCode = $language->code;
        $language->update($data);

        // If the code changed, rename the public site locale file (keep fa template).
        if ($oldCode !== $language->code) {
            if ($oldCode === 'fa') {
                // Never rename the Persian template file; create a copy for the new code instead.
                $this->createLocaleFile($language->code);
            } else {
                $this->renameLocaleFile($oldCode, $language->code);
            }
        }

        return response()->json([
            'message' => 'زبان با موفقیت به‌روزرسانی شد.',
            'data' => $language->fresh(),
        ]);
    }

    /**
     * Admin: Delete a language.
     * DELETE /languages/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $language = Language::find($id);

        if (!$language) {
            return response()->json(['message' => 'زبان یافت نشد'], 404);
        }

        if ($language->is_default) {
            return response()->json(['message' => 'زبان پیش‌فرض قابل حذف نیست.'], 422);
        }

        $code = $language->code;
        $language->delete();

        // Remove the locale file for the deleted language (keep the fa template).
        if ($code !== 'fa') {
            $this->deleteLocaleFile($code);
        }

        return response()->json([
            'message' => 'زبان با موفقیت حذف شد.',
        ]);
    }

    /**
     * Public: Serve a language's translations (parsed from its locale TS file)
     * as JSON. Lets the public site apply locale-editor changes at runtime
     * even on an already-built site, without rebuilding.
     * GET /v1/languages/{code}/locale
     */
    public function publicLocale(string $code): JsonResponse
    {
        $language = Language::where('code', $code)->first();

        if (!$language) {
            return response()->json(['message' => 'زبان یافت نشد'], 404);
        }

        $path = $this->localeFilePath($code);

        if (!file_exists($path)) {
            return response()->json(['message' => 'فایل ترجمه برای این زبان یافت نشد.'], 404);
        }

        try {
            $translations = TsLocaleParser::parse((string) file_get_contents($path));
        } catch (\Throwable $e) {
            $translations = null;
        }

        if ($translations === null) {
            return response()->json(['message' => 'محتوای فایل ترجمه قابل پردازش نیست.'], 422);
        }

        return response()->json([
            'data' => [
                'code' => $code,
                'translation' => $translations['translation'] ?? $translations,
            ],
        ]);
    }

    /**
     * Admin: Get the raw content of a language's locale file.
     * GET /languages/{code}/locale
     */
    public function locale(string $code): JsonResponse
    {
        $language = Language::where('code', $code)->first();

        if (!$language) {
            return response()->json(['message' => 'زبان یافت نشد'], 404);
        }

        $path = $this->localeFilePath($code);

        if (!file_exists($path)) {
            return response()->json(['message' => 'فایل ترجمه برای این زبان یافت نشد.'], 404);
        }

        return response()->json([
            'data' => [
                'code' => $code,
                'content' => file_get_contents($path),
            ],
        ]);
    }

    /**
     * Admin: Save the raw content of a language's locale file.
     * PUT /languages/{code}/locale
     */
    public function updateLocale(Request $request, string $code): JsonResponse
    {
        $language = Language::where('code', $code)->first();

        if (!$language) {
            return response()->json(['message' => 'زبان یافت نشد'], 404);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $content = $request->input('content');

        // Basic sanity check: the file must define and export the same language code.
        if (!Str::contains($content, 'const ' . $code . ' =') || !Str::contains($content, 'export default ' . $code)) {
            return response()->json(['message' => 'محتوای فایل ترجمه نامعتبر است. باید متغیر و export مربوط به کد زبان ' . $code . ' را داشته باشد.'], 422);
        }

        // The public site loads translations from this file at runtime, so make
        // sure it can actually be parsed before persisting it.
        try {
            $parsed = TsLocaleParser::parse($content);
        } catch (\Throwable $e) {
            $parsed = null;
        }

        if ($parsed === null) {
            return response()->json(['message' => 'محتوای فایل ترجمه قابل پردازش نیست. ساختار آبجکت ترجمه را بررسی کنید.'], 422);
        }

        try {
            $path = $this->localeFilePath($code);
            $dir = dirname($path);

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($path, $content);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'خطا در ذخیره فایل ترجمه: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'فایل ترجمه با موفقیت ذخیره شد.',
        ]);
    }

    /**
     * Create the public site locale file for a language by copying the Persian
     * template (fa.ts) and renaming the exported const to the language code.
     */
    protected function createLocaleFile(string $code): void
    {
        try {
            $template = $this->localeFilePath('fa');
            $target = $this->localeFilePath($code);

            if (!file_exists($template) || file_exists($target)) {
                return;
            }

            $content = file_get_contents($template);

            // const fa = { ... }  →  const <code> = { ... }
            $content = preg_replace('/const\s+fa\s*=/', 'const ' . $code . ' =', $content, 1);
            // export default fa;  →  export default <code>;
            $content = preg_replace('/export\s+default\s+fa\s*;/', 'export default ' . $code . ';', $content, 1);

            $dir = dirname($target);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($target, $content);
        } catch (\Throwable $e) {
            // The public site may not exist in production — locale file creation is best-effort.
            report($e);
        }
    }

    /**
     * Remove a language's locale file from the public site.
     */
    protected function deleteLocaleFile(string $code): void
    {
        try {
            $path = $this->localeFilePath($code);
            if (file_exists($path)) {
                unlink($path);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Rename a language's locale file (used when the language code changes).
     * The const name and export are updated to the new code.
     */
    protected function renameLocaleFile(string $oldCode, string $newCode): void
    {
        try {
            $oldPath = $this->localeFilePath($oldCode);
            $newPath = $this->localeFilePath($newCode);

            if (!file_exists($oldPath) || file_exists($newPath)) {
                return;
            }

            $content = file_get_contents($oldPath);
            $content = preg_replace('/const\s+' . preg_quote($oldCode, '/') . '\s*=/', 'const ' . $newCode . ' =', $content, 1);
            $content = preg_replace('/export\s+default\s+' . preg_quote($oldCode, '/') . '\s*;/', 'export default ' . $newCode . ';', $content, 1);

            $dir = dirname($newPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($newPath, $content);
            unlink($oldPath);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
