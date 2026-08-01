<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CountyProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CountyProjectController extends Controller
{
    /**
     * Public: Get all active county projects for the interactive map.
     */
    public function index(Request $request): JsonResponse
    {
        $counties = CountyProject::where('is_active', true)
            ->where('language', \App\Models\Language::resolve($request->input('lang')))
            ->orderBy('county_id')
            ->get();

        return response()->json([
            'data' => $counties,
        ]);
    }

    /**
     * Public: Get a single county project by county_id.
     */
    public function show(Request $request, string $countyId): JsonResponse
    {
        $county = CountyProject::where('county_id', $countyId)
            ->where('language', \App\Models\Language::resolve($request->input('lang')))
            ->where('is_active', true)
            ->first();

        if (!$county) {
            return response()->json(['message' => 'شهرستان یافت نشد'], 404);
        }

        return response()->json([
            'data' => $county,
        ]);
    }

    /**
     * Admin: Get all county projects with pagination and search.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = CountyProject::query()
            ->where('language', \App\Models\Language::resolve($request->input('lang')));

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('county_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = min((int) $request->input('per_page', 15), 50);
        $counties = $query->orderBy('county_id')
            ->paginate($perPage);

        return response()->json($counties);
    }

    /**
     * Admin: Update a single county project.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $county = CountyProject::find($id);

        if (!$county) {
            return response()->json(['message' => 'شهرستان یافت نشد'], 404);
        }

        $validator = Validator::make($request->all(), [
            'county_name' => 'sometimes|string|max:100',
            'road_projects_count' => 'sometimes|integer|min:0',
            'housing_units_count' => 'sometimes|integer|min:0',
            'urban_plans_count' => 'sometimes|integer|min:0',
            'road_progress' => 'sometimes|integer|min:0|max:100',
            'housing_progress' => 'sometimes|integer|min:0|max:100',
            'urban_progress' => 'sometimes|integer|min:0|max:100',
            'has_active_road_project' => 'sometimes|boolean',
            'has_housing_workshop' => 'sometimes|boolean',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $county->update(array_merge($request->all(), [
            'language' => \App\Models\Language::resolveRequest($request),
        ]));

        return response()->json([
            'message' => 'اطلاعات شهرستان با موفقیت به‌روزرسانی شد',
            'data' => $county->fresh(),
        ]);
    }

    /**
     * Admin: Update multiple counties at once (batch update).
     */
    public function updateBatch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'counties' => 'required|array|min:1',
            'counties.*.id' => 'required|integer|exists:county_projects,id',
            'counties.*.county_name' => 'sometimes|string|max:100',
            'counties.*.road_projects_count' => 'sometimes|integer|min:0',
            'counties.*.housing_units_count' => 'sometimes|integer|min:0',
            'counties.*.urban_plans_count' => 'sometimes|integer|min:0',
            'counties.*.road_progress' => 'sometimes|integer|min:0|max:100',
            'counties.*.housing_progress' => 'sometimes|integer|min:0|max:100',
            'counties.*.urban_progress' => 'sometimes|integer|min:0|max:100',
            'counties.*.has_active_road_project' => 'sometimes|boolean',
            'counties.*.has_housing_workshop' => 'sometimes|boolean',
            'counties.*.description' => 'nullable|string',
            'counties.*.is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updated = [];
        foreach ($request->input('counties') as $data) {
            $county = CountyProject::find($data['id']);
            if ($county) {
                $county->update(array_merge($data, [
                    'language' => \App\Models\Language::resolveRequest($request),
                ]));
                $updated[] = $county->fresh();
            }
        }

        return response()->json([
            'message' => 'اطلاعات شهرستان‌ها با موفقیت به‌روزرسانی شد',
            'data' => $updated,
        ]);
    }
}
