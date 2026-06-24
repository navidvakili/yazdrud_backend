<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TermCoupon;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CouponController extends Controller
{
    /**
     * Format a coupon for API response.
     */
    private function formatCoupon(TermCoupon $coupon): array
    {
        return [
            'id'             => $coupon->id,
            'title'          => $coupon->title,
            'code'           => $coupon->code,
            'type'           => $coupon->type,
            'type_discount'  => $coupon->type_discount,
            'value'          => $coupon->value,
            'value_formatted'=> $coupon->value_formatted,
            'course_id'      => $coupon->course_id,
            'course_title'   => $coupon->course?->title,
            'capacity'       => $coupon->capacity,
            'used_count'     => $coupon->used_count,
            'remaining'      => $coupon->remaining,
            'start_date'     => $coupon->start_date,
            'finish_date'    => $coupon->finish_date,
            'is_active'      => $coupon->is_active,
            'created_at'     => $coupon->created_at?->format('Y/m/d H:i'),
            'updated_at'     => $coupon->updated_at?->format('Y/m/d H:i'),
        ];
    }

    /**
     * List all coupons (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $query = TermCoupon::with('course');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $coupons = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => $coupons->map(function ($coupon) {
                return $this->formatCoupon($coupon);
            }),
            'meta' => [
                'current_page' => $coupons->currentPage(),
                'last_page'    => $coupons->lastPage(),
                'per_page'     => $coupons->perPage(),
                'total'        => $coupons->total(),
            ],
        ]);
    }

    /**
     * Get a single coupon by ID.
     */
    public function show($id): JsonResponse
    {
        $coupon = TermCoupon::with('course')->find($id);
        if (!$coupon) {
            return response()->json(['message' => 'بن تخفیف مورد نظر یافت نشد'], 404);
        }

        return response()->json([
            'data' => $this->formatCoupon($coupon),
        ]);
    }

    /**
     * Create a new coupon.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title'         => 'required|string|max:191',
            'code'          => 'required|string|max:191|unique:term_coupons,code',
            'type'          => 'required|in:discount,installment',
            'type_discount' => 'required|in:percent,money',
            'value'         => 'required|integer|min:0',
            'course_id'     => 'nullable|exists:courses,id',
            'capacity'      => 'nullable|integer|min:0',
            'start_date'    => 'nullable|string|max:191',
            'finish_date'   => 'nullable|string|max:191',
            'is_active'     => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $coupon = new TermCoupon();
        $coupon->title         = $request->title;
        $coupon->code          = strtoupper($request->code);
        $coupon->type          = $request->type;
        $coupon->type_discount = $request->type_discount;
        $coupon->value         = $request->value;
        $coupon->course_id     = $request->course_id;
        $coupon->term_id       = $request->term_id ?? 0;
        $coupon->capacity      = $request->capacity ?? 100;
        $coupon->used_count    = 0;
        $coupon->start_date    = $request->start_date ?? '';
        $coupon->finish_date   = $request->finish_date ?? '';
        $coupon->is_active     = $request->boolean('is_active', true);
        $coupon->save();

        return response()->json([
            'message' => 'بن تخفیف با موفقیت ایجاد شد',
            'data'    => $this->formatCoupon($coupon),
        ], 201);
    }

    /**
     * Update an existing coupon.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $coupon = TermCoupon::find($id);
        if (!$coupon) {
            return response()->json(['message' => 'بن تخفیف مورد نظر یافت نشد'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title'         => 'sometimes|required|string|max:191',
            'code'          => 'sometimes|required|string|max:191|unique:term_coupons,code,' . $id,
            'type'          => 'sometimes|required|in:discount,installment',
            'type_discount' => 'sometimes|required|in:percent,money',
            'value'         => 'sometimes|required|integer|min:0',
            'course_id'     => 'nullable|exists:courses,id',
            'capacity'      => 'nullable|integer|min:0',
            'start_date'    => 'nullable|string|max:191',
            'finish_date'   => 'nullable|string|max:191',
            'is_active'     => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->has('title')) $coupon->title = $request->title;
        if ($request->has('code')) $coupon->code = strtoupper($request->code);
        if ($request->has('type')) $coupon->type = $request->type;
        if ($request->has('type_discount')) $coupon->type_discount = $request->type_discount;
        if ($request->has('value')) $coupon->value = $request->value;
        if ($request->has('course_id')) $coupon->course_id = $request->course_id;
        if ($request->has('capacity')) $coupon->capacity = $request->capacity;
        if ($request->has('start_date')) $coupon->start_date = $request->start_date;
        if ($request->has('finish_date')) $coupon->finish_date = $request->finish_date;
        if ($request->has('is_active')) $coupon->is_active = $request->boolean('is_active');
        $coupon->save();

        return response()->json([
            'message' => 'بن تخفیف با موفقیت به‌روزرسانی شد',
            'data'    => $this->formatCoupon($coupon),
        ]);
    }

    /**
     * Delete a coupon.
     */
    public function destroy($id): JsonResponse
    {
        $coupon = TermCoupon::find($id);
        if (!$coupon) {
            return response()->json(['message' => 'بن تخفیف مورد نظر یافت نشد'], 404);
        }

        $coupon->delete();

        return response()->json([
            'message' => 'بن تخفیف با موفقیت حذف شد',
        ]);
    }

    /**
     * Validate a coupon code for a specific course.
     * POST /coupons/validate
     * Body: { code: "WELCOME10", course_id: 5 }
     */
    public function validate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code'      => 'required|string|max:191',
            'course_id' => 'nullable|exists:courses,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $code = strtoupper($request->code);

        $coupon = TermCoupon::where('code', $code)->first();

        if (!$coupon) {
            return response()->json([
                'valid'   => false,
                'message' => 'کد تخفیف معتبر نیست.',
            ]);
        }

        if (!$coupon->is_active) {
            return response()->json([
                'valid'   => false,
                'message' => 'این بن تخفیف غیرفعال شده است.',
            ]);
        }

        if ($coupon->used_count >= $coupon->capacity) {
            return response()->json([
                'valid'   => false,
                'message' => 'ظرفیت این بن تخفیف به پایان رسیده است.',
            ]);
        }

        // Check date validity
        if ($coupon->start_date && $coupon->start_date !== '0') {
            $now = now()->format('Y/m/d');
            if ($now < $coupon->start_date) {
                return response()->json([
                    'valid'   => false,
                    'message' => 'این بن تخفیف هنوز فعال نشده است.',
                ]);
            }
        }
        if ($coupon->finish_date && $coupon->finish_date !== '0') {
            $now = now()->format('Y/m/d');
            if ($now > $coupon->finish_date) {
                return response()->json([
                    'valid'   => false,
                    'message' => 'این بن تخفیف منقضی شده است.',
                ]);
            }
        }

        // Check course restriction
        if ($coupon->course_id && $request->filled('course_id')) {
            if ((int) $coupon->course_id !== (int) $request->course_id) {
                return response()->json([
                    'valid'   => false,
                    'message' => 'این بن تخفیف برای این دوره قابل استفاده نیست.',
                ]);
            }
        }

        // Calculate discount
        $discount = 0;
        $courseAmount = 0;
        if ($request->filled('course_id')) {
            $course = Course::find($request->course_id);
            if ($course) {
                $courseAmount = (int) $course->amount;
                if ($coupon->type_discount === 'percent') {
                    $discount = (int) round(($courseAmount * $coupon->value) / 100);
                } else {
                    $discount = $coupon->value;
                }
            }
        }

        return response()->json([
            'valid'    => true,
            'message'  => 'بن تخفیف معتبر است.',
            'coupon'   => [
                'id'             => $coupon->id,
                'title'          => $coupon->title,
                'code'           => $coupon->code,
                'type'           => $coupon->type,
                'type_discount'  => $coupon->type_discount,
                'value'          => $coupon->value,
                'discount'       => $discount,
            ],
        ]);
    }
}
