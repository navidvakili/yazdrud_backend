<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LanguageController extends Controller
{
    /**
     * Public: List active languages (ordered).
     */
    public function publicIndex(): JsonResponse
    {
        $languages = Language::active()
            ->orderBy('ordering')
            ->orderBy('code')
            ->get();

        return response()->json([
            'data' => $languages,
        ]);
    }

    /**
     * Admin: List all languages.
     */
    public function index(): JsonResponse
    {
        $languages = Language::orderBy('ordering')
            ->orderBy('code')
            ->get();

        return response()->json([
            'data' => $languages,
        ]);
    }

    /**
     * Admin: Create a new language.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:10|unique:languages,code',
            'name' => 'required|string|max:100',
            'name_en' => 'nullable|string|max:100',
            'dir' => 'nullable|in:rtl,ltr',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'ordering' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Only one default language allowed
        if (!empty($data['is_default'])) {
            Language::where('is_default', true)->update(['is_default' => false]);
        }

        $language = Language::create($data);

        return response()->json([
            'message' => 'زبان با موفقیت ایجاد شد.',
            'data' => $language,
        ], 201);
    }

    /**
     * Admin: Update a language.
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
            'dir' => 'nullable|in:rtl,ltr',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'ordering' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Only one default language allowed
        if (!empty($data['is_default'])) {
            Language::where('is_default', true)
                ->where('id', '!=', $language->id)
                ->update(['is_default' => false]);
        }

        $language->update($data);

        return response()->json([
            'message' => 'زبان با موفقیت به‌روزرسانی شد.',
            'data' => $language,
        ]);
    }

    /**
     * Admin: Delete a language.
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

        $language->delete();

        return response()->json(['message' => 'زبان با موفقیت حذف شد.']);
    }
}
