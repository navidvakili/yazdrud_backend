<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\NormalizesMediaUrls;
use App\Models\SliderProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SliderProjectController extends Controller
{
    use NormalizesMediaUrls;
    /**
     * Public: Get the active slider project with processed slides.
     * Returns the full project data suitable for rendering on the public site.
     */
    public function publicIndex(): JsonResponse
    {
        $project = SliderProject::active()->orderBy('sort_order')->first();

        if (!$project) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => $this->resolveSliderProjectData($project->project_data),
        ]);
    }

    /**
     * Admin: Get the current (single) slider project.
     * If none exists, creates a default empty one automatically.
     */
    public function current(): JsonResponse
    {
        $project = SliderProject::active()->orderBy('sort_order')->first();

        if (!$project) {
            $project = SliderProject::create([
                'title' => 'اسلایدهای وب‌سایت',
                'description' => 'اسلایدر اصلی وب‌سایت',
                'project_data' => [
                    'id' => 'default',
                    'title' => 'اسلایدهای وب‌سایت',
                    'description' => 'اسلایدر اصلی وب‌سایت',
                    'width' => 1240,
                    'height' => 720,
                    'autoPlay' => true,
                    'loop' => true,
                    'scrollSnap' => false,
                    'addonParticles' => false,
                    'addonWave' => false,
                    'addonTextMorph' => false,
                    'slides' => [],
                ],
                'is_active' => true,
                'sort_order' => 0,
            ]);
        }

        return response()->json([
            'data' => $project,
        ]);
    }

    /**
     * Admin: List all slider projects with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SliderProject::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 15), 50);
        $projects = $query->orderBy('sort_order')->paginate($perPage);

        return response()->json($projects);
    }

    /**
     * Admin: Show a single project.
     */
    public function show(int $id): JsonResponse
    {
        $project = SliderProject::find($id);

        if (!$project) {
            return response()->json(['message' => 'پروژه یافت نشد'], 404);
        }

        return response()->json([
            'data' => array_merge($project->toArray(), [
                'project_data' => $this->resolveSliderProjectData($project->project_data),
            ]),
        ]);
    }

    /**
     * Admin: Create a new slider project.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'project_data' => 'required|json',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['project_data'] = $this->normalizeSliderProjectData(json_decode($request->input('project_data'), true));

        if (!isset($data['sort_order'])) {
            $data['sort_order'] = SliderProject::max('sort_order') + 1;
        }

        $project = SliderProject::create($data);

        return response()->json([
            'message' => 'پروژه با موفقیت ایجاد شد.',
            'data' => array_merge($project->toArray(), [
                'project_data' => $this->resolveSliderProjectData($project->project_data),
            ]),
        ], 201);
    }

    /**
     * Admin: Update a project.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $project = SliderProject::find($id);

        if (!$project) {
            return response()->json(['message' => 'پروژه یافت نشد'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'project_data' => 'sometimes|json',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        if ($request->has('project_data')) {
            $data['project_data'] = $this->normalizeSliderProjectData(json_decode($request->input('project_data'), true));
        }

        $project->update($data);

        return response()->json([
            'message' => 'پروژه با موفقیت به‌روزرسانی شد.',
            'data' => array_merge($project->fresh()->toArray(), [
                'project_data' => $this->resolveSliderProjectData($project->fresh()->project_data),
            ]),
        ]);
    }

    /**
     * Admin: Delete a project.
     */
    public function destroy(int $id): JsonResponse
    {
        $project = SliderProject::find($id);

        if (!$project) {
            return response()->json(['message' => 'پروژه یافت نشد'], 404);
        }

        $project->delete();

        return response()->json([
            'message' => 'پروژه با موفقیت حذف شد.',
        ]);
    }

    /**
     * Admin: Toggle active status.
     */
    public function toggleActive(int $id): JsonResponse
    {
        $project = SliderProject::find($id);

        if (!$project) {
            return response()->json(['message' => 'پروژه یافت نشد'], 404);
        }

        // Deactivate all other projects, then activate this one
        SliderProject::where('id', '!=', $id)->update(['is_active' => false]);
        $project->update(['is_active' => !$project->is_active]);

        return response()->json([
            'message' => $project->is_active ? 'پروژه فعال شد.' : 'پروژه غیرفعال شد.',
            'data' => $project,
        ]);
    }
}
