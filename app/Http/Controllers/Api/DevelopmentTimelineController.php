<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DevelopmentTimelineItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @group Development Timeline — روند توسعه و تحول عمران شهری و جاده‌ای
 *
 * مدیریت آیتم‌های تایم‌لاین توسعه عمران شهری و جاده‌ای یزد
 */
class DevelopmentTimelineController extends Controller
{
    /**
     * Display a listing of the resource (public).
     *
     * @return JsonResponse
     */
    public function publicIndex(): JsonResponse
    {
        $items = DevelopmentTimelineItem::active()->ordered()->get();

        return response()->json([
            'success' => true,
            'data'    => $items,
        ]);
    }

    /**
     * Display a listing of the resource (admin).
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $items = DevelopmentTimelineItem::ordered()->get();

        return response()->json([
            'success' => true,
            'data'    => $items,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'year'        => 'required|string|max:50',
            'icon'        => 'nullable|string|max:100',
            'image_url'   => 'nullable|string|max:500',
            'type'        => 'nullable|string|in:road,urban,both',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'nullable|boolean',
        ], [
            'title.required'   => 'عنوان الزامی است.',
            'title.max'        => 'عنوان حداکثر ۲۵۵ کاراکتر می‌تواند باشد.',
            'year.required'    => 'سال یا بازه زمانی الزامی است.',
            'year.max'         => 'سال یا بازه زمانی حداکثر ۵۰ کاراکتر می‌تواند باشد.',
            'type.in'          => 'نوع باید یکی از مقادیر road, urban, both باشد.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اعتبارسنجی داده‌ها.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $item = DevelopmentTimelineItem::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'آیتم تایم‌لاین با موفقیت ایجاد شد.',
            'data'    => $item,
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $item = DevelopmentTimelineItem::find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'آیتم مورد نظر یافت نشد.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $item,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $item = DevelopmentTimelineItem::find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'آیتم مورد نظر یافت نشد.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title'       => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'year'        => 'sometimes|required|string|max:50',
            'icon'        => 'nullable|string|max:100',
            'image_url'   => 'nullable|string|max:500',
            'type'        => 'nullable|string|in:road,urban,both',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'nullable|boolean',
        ], [
            'title.required'   => 'عنوان الزامی است.',
            'title.max'        => 'عنوان حداکثر ۲۵۵ کاراکتر می‌تواند باشد.',
            'year.required'    => 'سال یا بازه زمانی الزامی است.',
            'year.max'         => 'سال یا بازه زمانی حداکثر ۵۰ کاراکتر می‌تواند باشد.',
            'type.in'          => 'نوع باید یکی از مقادیر road, urban, both باشد.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اعتبارسنجی داده‌ها.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $item->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'آیتم تایم‌لاین با موفقیت به‌روزرسانی شد.',
            'data'    => $item,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $item = DevelopmentTimelineItem::find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'آیتم مورد نظر یافت نشد.',
            ], 404);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'آیتم تایم‌لاین با موفقیت حذف شد.',
        ]);
    }
}
