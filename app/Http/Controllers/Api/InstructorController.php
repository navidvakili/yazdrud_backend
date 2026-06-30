<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourseInstructor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class InstructorController extends Controller
{
    /**
     * Format an instructor for API response.
     */
    private function formatInstructor(CourseInstructor $instructor): array
    {
        return [
            'id'         => $instructor->id,
            'name'       => $instructor->name,
            'specialty'  => $instructor->specialty,
            'bio'        => $instructor->bio,
            'photo'      => $instructor->photo_url,
            'active'     => (bool) $instructor->active,
            'created_at' => $instructor->created_at ? $instructor->created_at->format('Y/m/d') : null,
            'courses_count' => $instructor->courses()->count(),
        ];
    }

    /**
     * List all instructors.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CourseInstructor::query();

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('specialty', 'like', "%{$search}%");
            });
        }

        $query->orderBy('created_at', 'desc');
        $instructors = $query->get();

        return response()->json([
            'data' => $instructors->map(fn($i) => $this->formatInstructor($i)),
        ]);
    }

    /**
     * Get a single instructor by ID.
     */
    public function show($id): JsonResponse
    {
        $instructor = CourseInstructor::find($id);
        if (!$instructor) {
            return response()->json(['message' => 'استاد مورد نظر یافت نشد'], 404);
        }

        return response()->json([
            'data' => $this->formatInstructor($instructor),
        ]);
    }

    /**
     * Create a new instructor.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:255',
            'specialty' => 'nullable|string|max:255',
            'bio'       => 'nullable|string',
            'photo'     => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'active'    => 'nullable|in:0,1,true,false',
        ], [
            'name.required' => 'نام استاد الزامی است',
            'name.max'      => 'نام استاد نمی‌تواند بیش از ۲۵۵ کاراکتر باشد',
            'photo.image'   => 'فایل تصویر باید از نوع تصویر باشد',
            'photo.mimes'   => 'فرمت تصویر باید jpeg, png یا jpg باشد',
            'photo.max'     => 'حجم تصویر نباید بیشتر از ۲ مگابایت باشد',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'خطا در اعتبارسنجی داده‌های استاد',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $instructor = new CourseInstructor();
        $instructor->name      = $request->name;
        $instructor->specialty = $request->specialty;
        $instructor->bio       = $request->bio;
        $instructor->active    = $request->has('active') ? filter_var($request->active, FILTER_VALIDATE_BOOLEAN) : true;

        if ($request->hasFile('photo')) {
            $photoPath         = $request->file('photo')->store('instructors', 'public');
            $instructor->photo = $photoPath;
        }

        $instructor->save();

        return response()->json([
            'message' => 'استاد با موفقیت ایجاد شد',
            'data'    => $this->formatInstructor($instructor),
        ], 201);
    }

    /**
     * Update an existing instructor.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $instructor = CourseInstructor::find($id);
        if (!$instructor) {
            return response()->json(['message' => 'استاد مورد نظر یافت نشد'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'      => 'sometimes|required|string|max:255',
            'specialty' => 'nullable|string|max:255',
            'bio'       => 'nullable|string',
            'photo'     => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'active'    => 'nullable|in:0,1,true,false',
        ], [
            'name.required' => 'نام استاد الزامی است',
            'name.max'      => 'نام استاد نمی‌تواند بیش از ۲۵۵ کاراکتر باشد',
            'photo.image'   => 'فایل تصویر باید از نوع تصویر باشد',
            'photo.mimes'   => 'فرمت تصویر باید jpeg, png یا jpg باشد',
            'photo.max'     => 'حجم تصویر نباید بیشتر از ۲ مگابایت باشد',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'خطا در اعتبارسنجی داده‌های استاد',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if ($request->has('name')) {
            $instructor->name = $request->name;
        }
        if ($request->has('specialty')) {
            $instructor->specialty = $request->specialty;
        }
        if ($request->has('bio')) {
            $instructor->bio = $request->bio;
        }
        if ($request->has('active')) {
            $instructor->active = filter_var($request->active, FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->hasFile('photo')) {
            if ($instructor->photo && Storage::disk('public')->exists($instructor->photo)) {
                Storage::disk('public')->delete($instructor->photo);
            }
            $photoPath         = $request->file('photo')->store('instructors', 'public');
            $instructor->photo = $photoPath;
        }

        $instructor->save();

        return response()->json([
            'message' => 'استاد با موفقیت بروزرسانی شد',
            'data'    => $this->formatInstructor($instructor),
        ]);
    }

    /**
     * Delete an instructor.
     */
    public function destroy($id): JsonResponse
    {
        $instructor = CourseInstructor::find($id);
        if (!$instructor) {
            return response()->json(['message' => 'استاد مورد نظر یافت نشد'], 404);
        }

        // Set instructor_id to null for all courses using this instructor
        \App\Models\Course::where('instructor_id', $id)->update(['instructor_id' => null]);

        if ($instructor->photo && Storage::disk('public')->exists($instructor->photo)) {
            Storage::disk('public')->delete($instructor->photo);
        }

        $instructor->delete();

        return response()->json([
            'message' => 'استاد با موفقیت حذف شد',
        ]);
    }
}
