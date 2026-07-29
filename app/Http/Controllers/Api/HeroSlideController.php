<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HeroSlideController extends Controller
{
    /**
     * Public: Get all active slides ordered by sort_order.
     */
    public function index(): JsonResponse
    {
        $slides = HeroSlide::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $slides,
        ]);
    }

    /**
     * Admin: Get all slides with pagination and search.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = HeroSlide::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('tag', 'like', "%{$search}%")
                  ->orWhere('subtitle', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = min((int) $request->input('per_page', 15), 50);
        $slides = $query->orderBy('sort_order')
            ->paginate($perPage);

        return response()->json($slides);
    }

    /**
     * Admin: Create a new slide.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tag'                   => 'required|string|max:100',
            'title'                 => 'required|string|max:255',
            'subtitle'              => 'required|string',
            'badge'                 => 'required|string|max:255',
            'badge_icon'            => 'required|string|max:100',
            'bg_image'              => 'nullable|string|max:255',
            'primary_cta_text'      => 'required|string|max:255',
            'primary_cta_target'    => 'required|string|max:100',
            'secondary_cta_text'    => 'required|string|max:255',
            'secondary_cta_target'  => 'required|string|max:100',
            'sort_order'            => 'nullable|integer|min:0',
            'is_active'             => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        if (!isset($data['sort_order'])) {
            $data['sort_order'] = HeroSlide::max('sort_order') + 1;
        }

        $slide = HeroSlide::create($data);

        return response()->json([
            'message' => 'اسلاید با موفقیت ایجاد شد.',
            'data'    => $slide,
        ], 201);
    }

    /**
     * Admin: Update an existing slide.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $slide = HeroSlide::find($id);

        if (!$slide) {
            return response()->json(['message' => 'اسلاید یافت نشد'], 404);
        }

        $validator = Validator::make($request->all(), [
            'tag'                   => 'sometimes|string|max:100',
            'title'                 => 'sometimes|string|max:255',
            'subtitle'              => 'sometimes|string',
            'badge'                 => 'sometimes|string|max:255',
            'badge_icon'            => 'sometimes|string|max:100',
            'bg_image'              => 'nullable|string|max:255',
            'primary_cta_text'      => 'sometimes|string|max:255',
            'primary_cta_target'    => 'sometimes|string|max:100',
            'secondary_cta_text'    => 'sometimes|string|max:255',
            'secondary_cta_target'  => 'sometimes|string|max:100',
            'sort_order'            => 'nullable|integer|min:0',
            'is_active'             => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $slide->update($request->all());

        return response()->json([
            'message' => 'اسلاید با موفقیت به‌روزرسانی شد.',
            'data'    => $slide,
        ]);
    }

    /**
     * Admin: Delete a slide.
     */
    public function destroy(int $id): JsonResponse
    {
        $slide = HeroSlide::find($id);

        if (!$slide) {
            return response()->json(['message' => 'اسلاید یافت نشد'], 404);
        }

        $slide->delete();

        return response()->json([
            'message' => 'اسلاید با موفقیت حذف شد.',
        ]);
    }

    /**
     * Admin: Reorder slides (batch update sort_order).
     */
    public function reorder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'slides' => 'required|array',
            'slides.*.id' => 'required|integer|exists:hero_slides,id',
            'slides.*.sort_order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        foreach ($request->input('slides') as $item) {
            HeroSlide::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json([
            'message' => 'ترتیب اسلایدها با موفقیت به‌روزرسانی شد.',
        ]);
    }
}
