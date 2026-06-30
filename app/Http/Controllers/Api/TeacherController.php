<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TeacherController extends Controller
{
    /**
     * Format a teacher for API response.
     */
    private function formatTeacher(Teacher $teacher): array
    {
        return [
            'id'        => $teacher->id,
            'name'      => $teacher->name,
            'specialty' => $teacher->specialty,
            'bio'       => $teacher->bio,
            'photo'     => $teacher->photo_url,
            'active'    => (bool) $teacher->active,
            'created_at' => $teacher->created_at ? $teacher->created_at->format('Y/m/d') : null,
        ];
    }

    /**
     * List all teachers.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Teacher::query();

        // Filter by active status
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        // Search by name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('specialty', 'like', "%{$search}%");
            });
        }

        $query->orderBy('created_at', 'desc');

        $teachers = $query->get();

        return response()->json([
            'data' => $teachers->map(fn($t) => $this->formatTeacher($t)),
        ]);
    }

    /**
     * Get a single teacher by ID.
     */
    public function show($id): JsonResponse
    {
        $teacher = Teacher::find($id);
        if (!$teacher) {
            return response()->json(['message' => 'استاد مورد نظر یافت نشد'], 404);
        }

        return response()->json([
            'data' => $this->formatTeacher($teacher),
        ]);
    }

    /**
     * Create a new teacher.
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

        $teacher = new Teacher();
        $teacher->name      = $request->name;
        $teacher->specialty = $request->specialty;
        $teacher->bio       = $request->bio;
        $teacher->active    = $request->has('active') ? filter_var($request->active, FILTER_VALIDATE_BOOLEAN) : true;

        if ($request->hasFile('photo')) {
            $photoPath    = $request->file('photo')->store('teachers', 'public');
            $teacher->photo = $photoPath;
        }

        $teacher->save();

        return response()->json([
            'message' => 'استاد با موفقیت ایجاد شد',
            'data'    => $this->formatTeacher($teacher),
        ], 201);
    }

    /**
     * Update an existing teacher.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $teacher = Teacher::find($id);
        if (!$teacher) {
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
            $teacher->name = $request->name;
        }
        if ($request->has('specialty')) {
            $teacher->specialty = $request->specialty;
        }
        if ($request->has('bio')) {
            $teacher->bio = $request->bio;
        }
        if ($request->has('active')) {
            $teacher->active = filter_var($request->active, FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->hasFile('photo')) {
            // Delete old photo
            if ($teacher->photo && Storage::disk('public')->exists($teacher->photo)) {
                Storage::disk('public')->delete($teacher->photo);
            }
            $photoPath    = $request->file('photo')->store('teachers', 'public');
            $teacher->photo = $photoPath;
        }

        $teacher->save();

        return response()->json([
            'message' => 'استاد با موفقیت بروزرسانی شد',
            'data'    => $this->formatTeacher($teacher),
        ]);
    }

    /**
     * Delete a teacher.
     */
    public function destroy($id): JsonResponse
    {
        $teacher = Teacher::find($id);
        if (!$teacher) {
            return response()->json(['message' => 'استاد مورد نظر یافت نشد'], 404);
        }

        // Delete photo if exists
        if ($teacher->photo && Storage::disk('public')->exists($teacher->photo)) {
            Storage::disk('public')->delete($teacher->photo);
        }

        $teacher->delete();

        return response()->json([
            'message' => 'استاد با موفقیت حذف شد',
        ]);
    }
}
