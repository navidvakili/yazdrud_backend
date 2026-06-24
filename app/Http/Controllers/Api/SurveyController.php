<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourseSurvey;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SurveyController extends Controller
{
    /**
     * Format a survey for API response.
     */
    private function formatSurvey(CourseSurvey $survey): array
    {
        return [
            'id'                 => $survey->id,
            'course_id'          => $survey->course_id,
            'course_title'       => $survey->course?->title,
            'first_name'         => $survey->first_name,
            'last_name'          => $survey->last_name,
            'full_name'          => $survey->full_name,
            'phone_number'       => $survey->phone_number,
            'rating'             => $survey->rating,
            'suggestions'        => $survey->suggestions,
            'comment'            => $survey->comment,
            'ip_address'         => $survey->ip_address,
            'browser_fingerprint'=> $survey->browser_fingerprint,
            'created_at'         => $survey->created_at?->format('Y/m/d H:i'),
        ];
    }

    /**
     * List all surveys (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $query = CourseSurvey::with('course');

        // Filter by course
        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        // Search by name or phone
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        // Date range
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $surveys = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => $surveys->map(function ($survey) {
                return $this->formatSurvey($survey);
            }),
            'meta' => [
                'current_page' => $surveys->currentPage(),
                'last_page'    => $surveys->lastPage(),
                'per_page'     => $surveys->perPage(),
                'total'        => $surveys->total(),
            ],
        ]);
    }

    /**
     * Get a single survey by ID.
     */
    public function show($id): JsonResponse
    {
        $survey = CourseSurvey::with('course')->find($id);
        if (!$survey) {
            return response()->json(['message' => 'نظرسنجی مورد نظر یافت نشد'], 404);
        }

        return response()->json([
            'data' => $this->formatSurvey($survey),
        ]);
    }

    /**
     * Store a new survey (public - no auth required).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'course_id'    => 'nullable|exists:courses,id',
            'first_name'   => 'required|string|max:100',
            'last_name'    => 'required|string|max:100',
            'phone_number' => 'required|string|max:20',
            'rating'       => 'nullable|integer|min:1|max:5',
            'suggestions'  => 'nullable|string',
            'comment'      => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $survey = new CourseSurvey();
        $survey->course_id          = $request->course_id;
        $survey->first_name         = $request->first_name;
        $survey->last_name          = $request->last_name;
        $survey->phone_number       = $request->phone_number;
        $survey->rating             = $request->rating;
        $survey->suggestions        = $request->suggestions;
        $survey->comment            = $request->comment;
        $survey->ip_address         = $request->ip();
        $survey->browser_fingerprint = $request->input('browser_fingerprint');
        $survey->save();

        return response()->json([
            'message' => 'نظرسنجی با موفقیت ثبت شد',
            'data'    => $this->formatSurvey($survey),
        ], 201);
    }

    /**
     * Delete a survey.
     */
    public function destroy($id): JsonResponse
    {
        $survey = CourseSurvey::find($id);
        if (!$survey) {
            return response()->json(['message' => 'نظرسنجی مورد نظر یافت نشد'], 404);
        }

        $survey->delete();

        return response()->json([
            'message' => 'نظرسنجی با موفقیت حذف شد',
        ]);
    }

    /**
     * Get survey statistics.
     */
    public function statistics(): JsonResponse
    {
        $totalSurveys = CourseSurvey::count();

        $averageRating = CourseSurvey::whereNotNull('rating')->avg('rating');

        $surveysByCourse = CourseSurvey::with('course')
            ->selectRaw('course_id, COUNT(*) as count, AVG(rating) as avg_rating')
            ->whereNotNull('course_id')
            ->groupBy('course_id')
            ->get()
            ->map(function ($item) {
                return [
                    'course_id'    => $item->course_id,
                    'course_title' => $item->course?->title ?? 'نامشخص',
                    'count'        => (int) $item->count,
                    'avg_rating'   => round((float) $item->avg_rating, 1),
                ];
            });

        $ratingsBreakdown = CourseSurvey::whereNotNull('rating')
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->orderBy('rating')
            ->get()
            ->map(function ($item) {
                return [
                    'rating' => (int) $item->rating,
                    'count'  => (int) $item->count,
                ];
            });

        $recentSurveys = CourseSurvey::with('course')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(function ($survey) {
                return $this->formatSurvey($survey);
            });

        return response()->json([
            'data' => [
                'total_surveys'     => $totalSurveys,
                'average_rating'    => $averageRating ? round($averageRating, 1) : 0,
                'surveys_by_course' => $surveysByCourse,
                'ratings_breakdown' => $ratingsBreakdown,
                'recent_surveys'    => $recentSurveys,
            ],
        ]);
    }
}
