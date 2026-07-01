<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CourseResource;
use App\Models\Course;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PublicCourseController extends Controller
{
    /**
     * Apply date-based visibility rules:
     * - Exclude courses that have fully concluded (start_date AND end_date both in the past)
     * - Exclude courses where registration hasn't opened yet (registration_start_date > today)
     */
    private function applyDateFilters($query): void
    {
        $today = Carbon::today();

        // Only hide courses that have fully concluded (both start and end are past)
        $query->where(function ($q) use ($today) {
            $q->whereNull('start_date')       // No start date → always show
              ->orWhere('start_date', '>', $today)  // Future start → show
              ->orWhere(function ($q2) use ($today) {
                  // Started but hasn't ended yet → still show
                  $q2->where('start_date', '<=', $today)
                     ->where(function ($q3) use ($today) {
                         $q3->whereNull('end_date')          // No end date → still active
                            ->orWhere('end_date', '>=', $today);  // End date is today or future → still active
                     });
              });
        });

        // Registration not yet open → hide
        $query->where(function ($q) use ($today) {
            $q->whereNull('registration_start_date')
              ->orWhere('registration_start_date', '<=', $today);
        });
    }

    /**
     * Display a paginated listing of active courses with optional filters.
     *
     * @OA\Get(
     *     path="/api/public/courses",
     *     summary="لیست دوره‌های آموزشی فعال با فیلتر",
     *     tags={"Courses"},
     *     @OA\Response(response=200, description="List of courses")
     * )
     */
    public function index(Request $request)
    {
        $query = Course::where('active', true);
        $this->applyDateFilters($query);

        // Filter by category (title-based keyword matching)
        if ($request->filled('category')) {
            $category = $request->category;
            $query->where(function ($q) use ($category) {
                $q->where('title', 'like', '%' . $category . '%');
            });
        }

        // Search by title
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('instructor', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortField = $request->get('sort', 'created_at');
        $sortDir = $request->get('dir', 'desc');
        $allowedSorts = ['created_at', 'amount', 'start_date', 'title', 'registered_count'];
        if (!in_array($sortField, $allowedSorts)) {
            $sortField = 'created_at';
        }
        $query->orderBy($sortField, $sortDir);

        $perPage = min((int) $request->get('per_page', 12), 50);
        $courses = $query->paginate($perPage);

        return CourseResource::collection($courses);
    }

    /**
     * Display the specified course.
     *
     * @OA\Get(
     *     path="/api/public/courses/{id}",
     *     summary="مشاهده جزئیات یک دوره",
     *     tags={"Courses"},
     *     @OA\Response(response=200, description="Course details")
     * )
     */
    public function show($id)
    {
        $course = Course::findOrFail($id);

        if (!$course->active) {
            return response()->json(['message' => 'دوره مورد نظر یافت نشد.'], 404);
        }

        return new CourseResource($course);
    }

    /**
     * Get featured courses (latest active courses with available capacity).
     *
     * @OA\Get(
     *     path="/api/public/courses/featured",
     *     summary="دوره‌های ویژه و پیشنهادی",
     *     tags={"Courses"},
     *     @OA\Response(response=200, description="Featured courses")
     * )
     */
    public function featured()
    {
        $query = Course::where('active', true)
            ->whereJsonContains('sections', 'featured');
        $this->applyDateFilters($query);
        $courses = $query->orderBy('created_at', 'desc')
            ->take(6)
            ->get();

        return CourseResource::collection($courses);
    }

    /**
     * Get pre-registration courses (courses not yet active, coming soon).
     *
     * @OA\Get(
     *     path="/api/public/courses/pre-register",
     *     summary="دوره‌های قابل پیش‌ثبت‌نام",
     *     tags={"Courses"},
     *     @OA\Response(response=200, description="Pre-registration courses")
     * )
     */
    public function preRegister()
    {
        // Primary query: trust the explicit pre_register section tag.
        // No date filters — the admin's manual tagging is authoritative.
        $courses = Course::whereJsonContains('sections', 'pre_register')
            ->orderBy('created_at', 'desc')
            ->take(4)
            ->get();

        // Fallback: use old logic if no section-based courses
        if ($courses->isEmpty()) {
            $fallback = Course::query();
            $this->applyDateFilters($fallback);
            $fallback->where('active', false)
                ->orWhere(function ($q) {
                    $q->where('active', true)
                      ->where('capacity', '>', 0)
                      ->whereColumn('registered_count', '>=', 'capacity');
                })
                ->orderBy('created_at', 'desc')
                ->take(4);
            $courses = $fallback->get();
        }

        return CourseResource::collection($courses);
    }

    /**
     * Get free courses (price = 0).
     *
     * @OA\Get(
     *     path="/api/public/courses/free",
     *     summary="دوره‌ها و کارگاه‌های رایگان",
     *     tags={"Courses"},
     *     @OA\Response(response=200, description="Free courses")
     * )
     */
    public function free()
    {
        $query = Course::where('active', true)
            ->whereJsonContains('sections', 'free');
        $this->applyDateFilters($query);
        $courses = $query->orderBy('created_at', 'desc')
            ->take(4)
            ->get();

        // Fallback
        if ($courses->isEmpty()) {
            $fallback = Course::where('active', true);
            $this->applyDateFilters($fallback);
            $fallback->where(function ($q) {
                    $q->where('amount', 0)
                      ->orWhereNull('amount');
                })
                ->orderBy('created_at', 'desc')
                ->take(4);
            $courses = $fallback->get();
        }

        return CourseResource::collection($courses);
    }

    /**
     * Get site statistics.
     *
     * @OA\Get(
     *     path="/api/public/stats",
     *     summary="آمار کلی سایت",
     *     tags={"Stats"},
     *     @OA\Response(response=200, description="Site statistics")
     * )
     */
    public function stats()
    {
        $activeCourses = Course::where('active', true)->count();
        $totalRegistrations = \App\Models\Registertut::count();
        $instructors = Course::where('active', true)
            ->whereNotNull('instructor')
            ->distinct('instructor')
            ->count('instructor');

        return response()->json([
            'active_courses' => $activeCourses,
            'total_registrations' => $totalRegistrations,
            'instructors' => $instructors,
            'graduates' => \App\Models\Registertut::where('status', 'paid')
                ->orWhere('verified_receipt', true)
                ->count(),
        ]);
    }
}
