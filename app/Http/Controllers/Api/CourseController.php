<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Library\Crypt;
use App\Models\Registertut;
use App\Models\GatewayTransaction;
use App\Models\RegistertutsPayment;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CourseController extends Controller
{
    /**
     * Convert a date to Jalali (Shamsi) format using Verta.
     */
    private function toJalali($date, string $format = 'Y/m/d'): ?string
    {
        if (!$date) return null;
        try {
            return (new Verta($date))->format($format);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Format a course for API response.
     */
    private function formatCourse(Course $course): array
    {
        // Compute actual registration counts dynamically
        $totalRegistrations = $course->registrations_count ?? $course->registrations()->count();
        $confirmedCount = $course->confirmed_registrations_count ?? $course->confirmedRegistrations()->count();

        return [
            'id'               => $course->id,
            'group_id'         => $course->group_id,
            'group_title'      => $course->group?->title,
            'title'            => $course->title,
            'amount'           => $course->amount,
            'amount_formatted' => number_format(intval($course->amount)),
            'active'           => (bool) $course->active,
            'image'            => $course->image_url,
            'description'      => $course->description,
            'syllabus'         => $course->syllabus,
            'duration'         => $course->duration,
            'duration_text'    => $course->duration ? "{$course->duration} ساعت" : null,
            'instructor'       => $course->instructor,
            'start_date'       => $this->toJalali($course->start_date),
            'end_date'         => $this->toJalali($course->end_date),
            'capacity'         => $course->capacity,
            'registered_count' => $totalRegistrations,
            'confirmed_count'  => $confirmedCount,
            'remaining'        => $course->remaining_capacity,
            'is_available'     => $course->isAvailable(),
            'created_at'       => $this->toJalali($course->created_at, 'Y/m/d H:i'),
            'updated_at'       => $this->toJalali($course->updated_at, 'Y/m/d H:i'),
        ];
    }

    /**
     * Format a registration for API response.
     */
    private function formatRegistration(Registertut $reg): array
    {
        // Compute amount: for online payments use transaction price, for bank receipts use course amount
        $amount = 0;
        if ($reg->payment_method === 'online') {
            $amount = intval($reg->payment?->transaction?->price ?? 0);
        } else {
            $amount = intval($reg->course?->amount ?? 0);
        }

        return [
            'id'               => Crypt::encryptor('encrypt', $reg->id),
            'kodmeli'          => $reg->kodmeli,
            'course_id'        => $reg->course_id,
            'course_title'     => $reg->course?->title,
            'type'             => $reg->type,
            'type_text'        => $reg->type_text,
            'fullname'         => $reg->fullname,
            'id_edu'           => $reg->id_edu,
            'mobile'           => $reg->mobile,
            'email'            => $reg->email,
            'payment_method'   => $reg->payment_method,
            'payment_method_text' => $reg->payment_method === 'online' ? 'پرداخت آنلاین' : 'فیش بانکی',
            'bank_receipt'     => $reg->bank_receipt ? asset('storage/' . $reg->bank_receipt) : null,
            'bank_receipt_filename' => $reg->bank_receipt,
            'status'           => $reg->actual_status,
            'status_text'      => $reg->actual_status_text,
            'amount'           => $amount,
            'amount_formatted' => number_format($amount),
            'verified_receipt' => (bool) $reg->verified_receipt,
            'rejected_receipt' => (bool) $reg->rejected_receipt,
            'rejection_reason' => $reg->rejection_reason,
            'certificate_approved' => (bool) $reg->certificate_approved,
            'created_at'       => $this->toJalali($reg->created_at, 'Y/m/d H:i'),
            'verified_at'      => $this->toJalali($reg->verified_at, 'Y/m/d H:i'),
            'tracking_code'    => $reg->payment?->transaction?->tracking_code,
            'ref_id'           => $reg->payment?->transaction?->ref_id,
            'card_number'      => $reg->payment?->transaction?->card_number,
            'port'             => $reg->payment?->transaction?->port,
        ];
    }

    // ========================================================================
    // Course CRUD
    // ========================================================================

    /**
     * List all courses (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Course::query()
            ->with('group')
            ->withCount([
                'registrations',
                'confirmedRegistrations as confirmed_registrations_count',
            ]);

        // Filter by group
        if ($request->has('group_id')) {
            $query->where('group_id', $request->integer('group_id'));
        }

        // Filter by active status
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        // Search by title
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('instructor', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortField = $request->get('sort', 'created_at');
        $sortDir = $request->get('dir', 'desc');
        $query->orderBy($sortField, $sortDir);

        $perPage = $request->get('per_page', 15);
        $courses = $query->paginate($perPage);

        // Format courses
        $formatted = $courses->map(function ($course) {
            return $this->formatCourse($course);
        });

        return response()->json([
            'data' => $formatted,
            'meta' => [
                'current_page' => $courses->currentPage(),
                'last_page'    => $courses->lastPage(),
                'per_page'     => $courses->perPage(),
                'total'        => $courses->total(),
            ],
        ]);
    }

    /**
     * Get a single course by ID.
     */
    public function show($id): JsonResponse
    {
        $course = Course::withCount([
            'registrations',
            'confirmedRegistrations as confirmed_registrations_count',
        ])->find($id);
        if (!$course) {
            return response()->json(['message' => 'دوره آموزشی مورد نظر یافت نشد'], 404);
        }

        return response()->json([
            'data' => $this->formatCourse($course),
        ]);
    }

    /**
     * Create a new course.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255',
            'amount'      => 'required|numeric|min:0',
            'active'      => 'required|in:0,1,true,false',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'description' => 'nullable|string',
            'syllabus'    => 'nullable|string',
            'duration'    => 'nullable|integer|min:0',
            'instructor'  => 'nullable|string|max:255',
            'group_id'    => 'nullable|integer|exists:course_groups,id',
            'start_date'  => 'nullable|date_format:Y/m/d',
            'end_date'    => 'nullable|date_format:Y/m/d|after_or_equal:start_date',
            'capacity'    => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $course = new Course();
        $course->group_id    = $request->group_id;
        $course->title       = $request->title;
        $course->amount      = str_replace(',', '', (string) $request->amount);
        $course->active      = filter_var($request->active, FILTER_VALIDATE_BOOLEAN);
        $course->description = $request->description;
        $course->syllabus    = $request->syllabus;
        $course->duration    = $request->duration;
        $course->instructor  = $request->instructor;

        if ($request->start_date) {
            $course->start_date = date('Y-m-d', strtotime($request->start_date));
        }
        if ($request->end_date) {
            $course->end_date = date('Y-m-d', strtotime($request->end_date));
        }

        $course->capacity         = $request->capacity ?? 0;
        $course->registered_count = 0;

        if ($request->hasFile('image')) {
            $imagePath     = $request->file('image')->store('courses', 'public');
            $course->image = $imagePath;
        }

        $course->save();

        return response()->json([
            'message' => 'دوره آموزشی با موفقیت ایجاد شد',
            'data'    => $this->formatCourse($course),
        ], 201);
    }

    /**
     * Update an existing course.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $course = Course::find($id);
        if (!$course) {
            return response()->json(['message' => 'دوره آموزشی مورد نظر یافت نشد'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title'       => 'sometimes|required|string|max:255',
            'amount'      => 'sometimes|required|numeric|min:0',
            'active'      => 'sometimes|required|in:0,1,true,false',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'description' => 'nullable|string',
            'syllabus'    => 'nullable|string',
            'duration'    => 'nullable|integer|min:0',
            'instructor'  => 'nullable|string|max:255',
            'group_id'    => 'nullable|integer|exists:course_groups,id',
            'start_date'  => 'nullable|date_format:Y/m/d',
            'end_date'    => 'nullable|date_format:Y/m/d|after_or_equal:start_date',
            'capacity'    => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->has('title')) {
            $course->title = $request->title;
        }
        if ($request->has('amount')) {
            $course->amount = str_replace(',', '', (string) $request->amount);
        }
        if ($request->has('active')) {
            $course->active = filter_var($request->active, FILTER_VALIDATE_BOOLEAN);
        }
        if ($request->has('description')) {
            $course->description = $request->description;
        }
        if ($request->has('syllabus')) {
            $course->syllabus = $request->syllabus;
        }
        if ($request->has('duration')) {
            $course->duration = $request->duration;
        }
        if ($request->has('instructor')) {
            $course->instructor = $request->instructor;
        }
        if ($request->has('group_id')) {
            $course->group_id = $request->group_id;
        }
        if ($request->has('start_date')) {
            $course->start_date = $request->start_date ? date('Y-m-d', strtotime($request->start_date)) : null;
        }
        if ($request->has('end_date')) {
            $course->end_date = $request->end_date ? date('Y-m-d', strtotime($request->end_date)) : null;
        }
        if ($request->has('capacity')) {
            $course->capacity = $request->capacity ?? 0;
        }

        if ($request->hasFile('image')) {
            // Delete old image
            if ($course->image && Storage::disk('public')->exists($course->image)) {
                Storage::disk('public')->delete($course->image);
            }
            $imagePath  = $request->file('image')->store('courses', 'public');
            $course->image = $imagePath;
        }

        $course->save();

        return response()->json([
            'message' => 'دوره آموزشی با موفقیت به‌روزرسانی شد',
            'data'    => $this->formatCourse($course),
        ]);
    }

    /**
     * Delete a course.
     */
    public function destroy($id): JsonResponse
    {
        $course = Course::find($id);
        if (!$course) {
            return response()->json(['message' => 'دوره آموزشی مورد نظر یافت نشد'], 404);
        }

        // Check if there are registrations
        $registers = Registertut::where('course_id', $course->id)->count();
        if ($registers > 0) {
            return response()->json([
                'message' => 'این دوره دارای ثبت‌نام کننده است، امکان حذف وجود ندارد',
            ], 409);
        }

        if ($course->image && Storage::disk('public')->exists($course->image)) {
            Storage::disk('public')->delete($course->image);
        }

        $course->delete();

        return response()->json([
            'message' => 'دوره آموزشی با موفقیت حذف شد',
        ]);
    }

    /**
     * Toggle active status of a course.
     */
    public function toggleActive($id): JsonResponse
    {
        $course = Course::find($id);
        if (!$course) {
            return response()->json(['message' => 'دوره آموزشی مورد نظر یافت نشد'], 404);
        }

        $course->active = !$course->active;
        $course->save();

        $statusText = $course->active ? 'فعال' : 'غیرفعال';

        return response()->json([
            'message' => "وضعیت دوره به {$statusText} تغییر یافت",
            'data'    => $this->formatCourse($course),
        ]);
    }

    // ========================================================================
    // Course Registrations
    // ========================================================================

    /**
     * List registrations for a specific course.
     */
    public function registrations($courseId): JsonResponse
    {
        $course = Course::find($courseId);
        if (!$course) {
            return response()->json(['message' => 'دوره آموزشی مورد نظر یافت نشد'], 404);
        }

        $registrations = Registertut::with(['course', 'payment.transaction'])
            ->where('course_id', $courseId)
            ->where(function ($q) {
                // For online payments, only include if transaction is SUCCEED
                $q->where('payment_method', '!=', 'online')
                  ->orWhereHas('payment.transaction', function ($q2) {
                      $q2->where('status', 'SUCCEED');
                  });
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $registrations->map(function ($reg) {
                return $this->formatRegistration($reg);
            }),
        ]);
    }

    /**
     * List all registrations across all courses (for admin).
     */
    public function allRegistrations(Request $request): JsonResponse
    {
        $query = Registertut::with(['course', 'payment.transaction']);

        // Filter by course
        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            // For bank receipt status
            switch ($request->status) {
                case 'verified':
                    // Both online paid and bank receipt approved
                    $query->where(function ($q) {
                        $q->where('verified_receipt', true)
                          ->orWhere(function ($q2) {
                              $q2->where('payment_method', 'online')
                                 ->whereHas('payment.transaction', function ($q3) {
                                     $q3->where('status', 'SUCCEED');
                                 });
                          });
                    });
                    break;
                case 'pending':
                    $query->where('payment_method', 'bank')
                        ->where('verified_receipt', false)
                        ->where('rejected_receipt', false);
                    break;
                case 'approved':
                    $query->where('verified_receipt', true);
                    break;
                case 'rejected':
                    $query->where('rejected_receipt', true);
                    break;
                case 'paid':
                    $query->where('payment_method', 'online')
                        ->whereHas('payment.transaction', function ($q) {
                            $q->where('status', 'SUCCEED');
                        });
                    break;
            }
        }

        $registrations = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => $registrations->map(function ($reg) {
                return $this->formatRegistration($reg);
            }),
            'meta' => [
                'current_page' => $registrations->currentPage(),
                'last_page'    => $registrations->lastPage(),
                'per_page'     => $registrations->perPage(),
                'total'        => $registrations->total(),
            ],
        ]);
    }

    /**
     * Approve a bank receipt.
     */
    public function approveReceipt($registrationId): JsonResponse
    {
        $reg = Registertut::find($registrationId);
        if (!$reg) {
            return response()->json(['message' => 'ثبت‌نام مورد نظر یافت نشد'], 404);
        }

        $reg->verified_receipt = true;
        $reg->verified_at = now();
        $reg->rejected_receipt = false;
        $reg->rejected_at = null;
        $reg->rejection_reason = null;
        $reg->save();

        // Update course registered count
        $course = Course::find($reg->course_id);
        if ($course) {
            $paidCount = Registertut::where('course_id', $course->id)
                ->where(function ($q) {
                    $q->where('verified_receipt', true)
                      ->orWhere(function ($q2) {
                          $q2->where('payment_method', 'online')
                             ->whereHas('payment.transaction', function ($q3) {
                                 $q3->where('status', 'SUCCEED');
                             });
                      });
                })
                ->count();
            $course->registered_count = $paidCount;
            $course->save();
        }

        return response()->json([
            'message' => 'فیش بانکی با موفقیت تایید شد',
            'data'    => $this->formatRegistration($reg),
        ]);
    }

    /**
     * Reject a bank receipt.
     */
    public function rejectReceipt(Request $request, $registrationId): JsonResponse
    {
        $reg = Registertut::find($registrationId);
        if (!$reg) {
            return response()->json(['message' => 'ثبت‌نام مورد نظر یافت نشد'], 404);
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $reg->rejected_receipt = true;
        $reg->rejected_at = now();
        $reg->verified_receipt = false;
        $reg->verified_at = null;
        $reg->rejection_reason = $request->rejection_reason;
        $reg->save();

        return response()->json([
            'message' => 'فیش بانکی رد شد',
            'data'    => $this->formatRegistration($reg),
        ]);
    }

    // ========================================================================
    // Registration Stats
    // ========================================================================

    /**
     * Get statistics for courses and registrations.
     */
    public function statistics(): JsonResponse
    {
        $totalCourses = Course::count();
        $activeCourses = Course::where('active', true)->count();
        $totalRegistrations = Registertut::count();
        $pendingReceipts = Registertut::where('payment_method', 'bank')
            ->where('verified_receipt', false)
            ->where('rejected_receipt', false)
            ->count();

        // Registration stats by course
        $topCourses = Course::withCount('registrations')
            ->orderBy('registrations_count', 'desc')
            ->take(5)
            ->get()
            ->map(function ($course) {
                return [
                    'id'    => $course->id,
                    'title' => $course->title,
                    'count' => $course->registrations_count,
                ];
            });

        return response()->json([
            'data' => [
                'total_courses'       => $totalCourses,
                'active_courses'      => $activeCourses,
                'total_registrations' => $totalRegistrations,
                'pending_receipts'    => $pendingReceipts,
                'top_courses'         => $topCourses,
            ],
        ]);
    }
}
