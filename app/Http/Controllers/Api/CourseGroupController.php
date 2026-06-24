<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourseGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CourseGroupController extends Controller
{
    /**
     * List all course groups.
     */
    public function index(): JsonResponse
    {
        $groups = CourseGroup::orderBy('title')->get();

        return response()->json([
            'data' => $groups,
        ]);
    }

    /**
     * Create a new course group.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255|unique:course_groups,title',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $group = CourseGroup::create([
            'title' => $request->title,
        ]);

        return response()->json([
            'message' => 'گروه آموزشی با موفقیت ایجاد شد',
            'data'    => $group,
        ], 201);
    }

    /**
     * Update a course group.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $group = CourseGroup::find($id);
        if (!$group) {
            return response()->json(['message' => 'گروه آموزشی مورد نظر یافت نشد'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255|unique:course_groups,title,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $group->update([
            'title' => $request->title,
        ]);

        return response()->json([
            'message' => 'گروه آموزشی با موفقیت به‌روزرسانی شد',
            'data'    => $group->fresh(),
        ]);
    }

    /**
     * Delete a course group.
     */
    public function destroy($id): JsonResponse
    {
        $group = CourseGroup::find($id);
        if (!$group) {
            return response()->json(['message' => 'گروه آموزشی مورد نظر یافت نشد'], 404);
        }

        // Check if any courses are using this group
        if ($group->courses()->count() > 0) {
            return response()->json([
                'message' => 'این گروه دارای دوره آموزشی است، امکان حذف وجود ندارد',
            ], 409);
        }

        $group->delete();

        return response()->json([
            'message' => 'گروه آموزشی با موفقیت حذف شد',
        ]);
    }
}
