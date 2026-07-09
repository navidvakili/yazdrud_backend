<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TermCoupon;
use App\Models\VoucherInstallmentItem;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Hekmatinasser\Verta\Verta;

class CouponController extends Controller
{
    /**
     * Format a coupon for API response.
     */
    private function formatCoupon(TermCoupon $coupon): array
    {
        $installmentItems = $coupon->relationLoaded('installmentItems')
            ? $coupon->installmentItems
            : $coupon->installmentItems()->get();

        return [
            'id'                 => $coupon->id,
            'title'              => $coupon->title,
            'code'               => $coupon->code,
            'type'               => $coupon->type,
            'type_discount'      => $coupon->type_discount,
            'value'              => $coupon->value,
            'value_formatted'    => $coupon->value_formatted,
            'course_id'          => $coupon->course_id,
            'course_title'       => $coupon->course?->title,
            'group_id'           => $coupon->group_id,
            'group_title'        => $coupon->group?->title,
            'capacity'           => $coupon->capacity,
            'used_count'         => $coupon->used_count,
            'remaining'          => $coupon->remaining,
            'start_date'         => $coupon->start_date,
            'finish_date'        => $coupon->finish_date,
            'is_active'          => $coupon->is_active,
            'max_discount'       => $coupon->max_discount,
            'national_code'      => $coupon->national_code,
            'enable_installment' => $coupon->enable_installment,
            'prepayment_amount'  => $coupon->prepayment_amount,
            'payment_method'     => $coupon->payment_method,
            'installment_items'  => $installmentItems->map(function ($item) {
                return [
                    'id'        => $item->id,
                    'title'     => $item->title,
                    'amount'    => $item->amount,
                    'due_date'  => $item->due_date,
                    'sort_order'=> $item->sort_order,
                ];
            }),
            'created_at'         => $coupon->created_at?->format('Y/m/d H:i'),
            'updated_at'         => $coupon->updated_at?->format('Y/m/d H:i'),
        ];
    }

    /**
     * Save installment items for a coupon (replace all existing).
     */
    private function saveInstallmentItems(TermCoupon $coupon, array $items): void
    {
        $coupon->installmentItems()->delete();

        foreach ($items as $index => $item) {
            $coupon->installmentItems()->create([
                'title'      => $item['title'] ?? ('قسط ' . ($index + 1)),
                'amount'     => (int) ($item['amount'] ?? 0),
                'due_date'   => $this->convertPersianToEnglish($item['due_date'] ?? ''),
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * Check if the coupon's installment plan can be edited.
     * Returns true if no payment has been made against any installment.
     */
    private function canEditInstallments(TermCoupon $coupon): bool
    {
        // Check if any registration using this coupon has paid installments
        return !$coupon->registrations()
            ->whereHas('installments', function ($q) {
                $q->where('status', 'paid');
            })
            ->exists();
    }

    /**
     * List all coupons (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $query = TermCoupon::with(['course', 'group', 'installmentItems']);

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
        $coupon = TermCoupon::with(['course', 'group', 'installmentItems'])->find($id);
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
            'title'              => 'required|string|max:191',
            'code'               => 'required|string|max:191|unique:term_coupons,code',
            'type'               => 'required|in:discount,installment',
            'type_discount'      => 'required|in:percent,money',
            'value'              => 'required|integer|min:0',
            'course_id'          => 'nullable|exists:courses,id',
            'group_id'           => 'nullable|exists:course_groups,id',
            'capacity'           => 'nullable|integer|min:0',
            'start_date'         => 'nullable|string|max:191',
            'finish_date'        => 'nullable|string|max:191',
            'is_active'          => 'nullable|boolean',
            'max_discount'       => 'nullable|integer|min:0',
            'national_code'      => 'nullable|string|max:1000',
            'enable_installment' => 'nullable|boolean',
            'prepayment_amount'  => 'nullable|integer|min:0',
            'payment_method'     => 'nullable|in:online,offline',
            'installment_items'  => 'nullable|array',
            'installment_items.*.title'     => 'required_with:installment_items|string|max:191',
            'installment_items.*.amount'    => 'required_with:installment_items|integer|min:0',
            'installment_items.*.due_date'  => 'required_with:installment_items|string|max:191',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $coupon = new TermCoupon();
            $coupon->title              = $request->title;
            $coupon->code               = strtoupper($request->code);
            $coupon->type               = $request->type;
            $coupon->type_discount      = $request->type_discount;
            $coupon->value              = $request->value;
            $coupon->course_id          = $request->course_id;
            $coupon->group_id           = $request->group_id;
            $coupon->term_id            = $request->term_id ?? null;
            $coupon->capacity           = $request->capacity ?? 100;
            $coupon->used_count         = 0;
            $coupon->start_date         = $this->convertPersianToEnglish($request->start_date ?? '');
            $coupon->finish_date        = $this->convertPersianToEnglish($request->finish_date ?? '');
            $coupon->is_active          = $request->boolean('is_active', true);
            $coupon->max_discount       = $request->max_discount;
            $coupon->national_code      = $request->national_code;
            $coupon->enable_installment = $request->boolean('enable_installment', false);
            $coupon->prepayment_amount  = $request->prepayment_amount;
            $coupon->payment_method     = $request->payment_method;
            $coupon->save();

            // Save installment items if provided
            if ($request->has('installment_items') && is_array($request->installment_items)) {
                $this->saveInstallmentItems($coupon, $request->installment_items);
            }

            DB::commit();

            return response()->json([
                'message' => 'بن تخفیف با موفقیت ایجاد شد',
                'data'    => $this->formatCoupon($coupon->fresh(['installmentItems'])),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'خطا در ایجاد بن تخفیف: ' . $e->getMessage()], 500);
        }
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

        // Check if installment plan can be edited (no paid installments yet)
        if ($request->has('installment_items') || $request->has('enable_installment')) {
            if (!$this->canEditInstallments($coupon)) {
                return response()->json([
                    'message' => 'امکان ویرایش طرح اقساط وجود ندارد. اولین قسط این بن قبلاً پرداخت شده است.',
                ], 422);
            }
        }

        $validator = Validator::make($request->all(), [
            'title'              => 'sometimes|required|string|max:191',
            'code'               => 'sometimes|required|string|max:191|unique:term_coupons,code,' . $id,
            'type'               => 'sometimes|required|in:discount,installment',
            'type_discount'      => 'sometimes|required|in:percent,money',
            'value'              => 'sometimes|required|integer|min:0',
            'course_id'          => 'nullable|exists:courses,id',
            'group_id'           => 'nullable|exists:course_groups,id',
            'capacity'           => 'nullable|integer|min:0',
            'used_count'         => 'nullable|integer|min:0',
            'start_date'         => 'nullable|string|max:191',
            'finish_date'        => 'nullable|string|max:191',
            'is_active'          => 'nullable|boolean',
            'max_discount'       => 'nullable|integer|min:0',
            'national_code'      => 'nullable|string|max:1000',
            'enable_installment' => 'nullable|boolean',
            'prepayment_amount'  => 'nullable|integer|min:0',
            'payment_method'     => 'nullable|in:online,offline',
            'installment_items'  => 'nullable|array',
            'installment_items.*.title'     => 'required_with:installment_items|string|max:191',
            'installment_items.*.amount'    => 'required_with:installment_items|integer|min:0',
            'installment_items.*.due_date'  => 'required_with:installment_items|string|max:191',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            if ($request->has('title')) $coupon->title = $request->title;
            if ($request->has('code')) $coupon->code = strtoupper($request->code);
            if ($request->has('type')) $coupon->type = $request->type;
            if ($request->has('type_discount')) $coupon->type_discount = $request->type_discount;
            if ($request->has('value')) $coupon->value = $request->value;
            if ($request->has('course_id')) $coupon->course_id = $request->course_id;
            if ($request->has('group_id')) $coupon->group_id = $request->group_id;
            if ($request->has('capacity')) $coupon->capacity = $request->capacity;
            if ($request->has('used_count')) $coupon->used_count = $request->used_count;
            if ($request->has('start_date')) $coupon->start_date = $this->convertPersianToEnglish($request->start_date);
            if ($request->has('finish_date')) $coupon->finish_date = $this->convertPersianToEnglish($request->finish_date);
            if ($request->has('is_active')) $coupon->is_active = $request->boolean('is_active');
            if ($request->has('max_discount')) $coupon->max_discount = $request->max_discount;
            if ($request->has('national_code')) $coupon->national_code = $request->national_code;
            if ($request->has('enable_installment')) $coupon->enable_installment = $request->boolean('enable_installment');
            if ($request->has('prepayment_amount')) $coupon->prepayment_amount = $request->prepayment_amount;
            if ($request->has('payment_method')) $coupon->payment_method = $request->payment_method;
            $coupon->save();

            // Save installment items if provided
            if ($request->has('installment_items') && is_array($request->installment_items)) {
                $this->saveInstallmentItems($coupon, $request->installment_items);
            }

            DB::commit();

            return response()->json([
                'message' => 'بن تخفیف با موفقیت به‌روزرسانی شد',
                'data'    => $this->formatCoupon($coupon->fresh(['installmentItems'])),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'خطا در به‌روزرسانی بن تخفیف: ' . $e->getMessage()], 500);
        }
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

        if ($coupon->used_count > 0) {
            return response()->json([
                'message' => 'این بن تخفیف قبلاً استفاده شده است و قابل حذف نمی‌باشد.',
            ], 422);
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
            'code'          => 'required|string|max:191',
            'course_id'     => 'nullable|exists:courses,id',
            'national_code' => 'nullable|string|max:1000',
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

        // Check date validity (dates are stored with Western digits; Verta also returns Western digits)
        $todayJalali = Verta::now()->format('Y/m/d');
        if ($coupon->start_date && $coupon->start_date !== '0') {
            $startDate = $this->convertPersianToEnglish($coupon->start_date);
            if ($todayJalali < $startDate) {
                return response()->json([
                    'valid'   => false,
                    'message' => 'این بن تخفیف هنوز فعال نشده است.',
                ]);
            }
        }
        if ($coupon->finish_date && $coupon->finish_date !== '0') {
            $finishDate = $this->convertPersianToEnglish($coupon->finish_date);
            if ($todayJalali > $finishDate) {
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

        // Check group restriction
        if ($coupon->group_id && $request->filled('course_id')) {
            $course = Course::find($request->course_id);
            if (!$course || (int) $course->group_id !== (int) $coupon->group_id) {
                return response()->json([
                    'valid'   => false,
                    'message' => 'این بن تخفیف برای دوره‌های این گروه قابل استفاده نیست.',
                ]);
            }
        }

        // Check national code restriction
        if ($coupon->national_code && $request->filled('national_code')) {
            $allowedCodes = array_map('trim', explode(',', $coupon->national_code));
            if (!in_array($request->national_code, $allowedCodes, true)) {
                return response()->json([
                    'valid'   => false,
                    'message' => 'این بن تخفیف فقط برای کد ملی مشخص‌شده قابل استفاده است.',
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

        // Apply max_discount cap
        if ($coupon->max_discount && $discount > $coupon->max_discount) {
            $discount = (int) $coupon->max_discount;
        }

        // Build response with installment info if applicable
        $response = [
            'id'             => $coupon->id,
            'title'          => $coupon->title,
            'code'           => $coupon->code,
            'type'           => $coupon->type,
            'type_discount'  => $coupon->type_discount,
            'value'          => $coupon->value,
            'discount'       => $discount,
        ];

        // Include installment plan if enabled
        if ($coupon->enable_installment) {
            $installmentItems = $coupon->installmentItems()->orderBy('sort_order')->get();
            $response['enable_installment'] = true;
            $response['prepayment_amount'] = $coupon->prepayment_amount;
            $response['payment_method'] = $coupon->payment_method;
            $response['installment_items'] = $installmentItems->map(function ($item) {
                return [
                    'id'        => $item->id,
                    'title'     => $item->title,
                    'amount'    => $item->amount,
                    'due_date'  => $item->due_date,
                ];
            });
        }

        return response()->json([
            'valid'    => true,
            'message'  => 'بن تخفیف معتبر است.',
            'coupon'   => $response,
        ]);
    }

    /**
     * Generate a unique random coupon code.
     * GET /coupons/generate-code
     */
    public function generateCode(Request $request): JsonResponse
    {
        $length = $request->get('length', 8);
        $prefix = $request->get('prefix', '');

        do {
            $code = $prefix;
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            for ($i = 0; $i < $length; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (TermCoupon::where('code', $code)->exists());

        return response()->json([
            'data' => [
                'code' => $code,
            ],
        ]);
    }

    /**
     * Generate a coupon with auto-generated code and create it.
     * POST /coupons/generate
     */
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title'              => 'required|string|max:191',
            'type'               => 'required|in:discount,installment',
            'type_discount'      => 'required|in:percent,money',
            'value'              => 'required|integer|min:0',
            'course_id'          => 'nullable|exists:courses,id',
            'group_id'           => 'nullable|exists:course_groups,id',
            'capacity'           => 'nullable|integer|min:0',
            'start_date'         => 'nullable|string|max:191',
            'finish_date'        => 'nullable|string|max:191',
            'is_active'          => 'nullable|boolean',
            'max_discount'       => 'nullable|integer|min:0',
            'national_code'      => 'nullable|string|max:20',
            'enable_installment' => 'nullable|boolean',
            'prepayment_amount'  => 'nullable|integer|min:0',
            'payment_method'     => 'nullable|in:online,offline',
            'installment_items'  => 'nullable|array',
            'installment_items.*.title'     => 'required_with:installment_items|string|max:191',
            'installment_items.*.amount'    => 'required_with:installment_items|integer|min:0',
            'installment_items.*.due_date'  => 'required_with:installment_items|string|max:191',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Generate unique code
        $prefix = $request->get('prefix', '');
        do {
            $code = $prefix;
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            for ($i = 0; $i < 8; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (TermCoupon::where('code', $code)->exists());

        DB::beginTransaction();
        try {
            $coupon = new TermCoupon();
            $coupon->title              = $request->title;
            $coupon->code               = $code;
            $coupon->type               = $request->type;
            $coupon->type_discount      = $request->type_discount;
            $coupon->value              = $request->value;
            $coupon->course_id          = $request->course_id;
            $coupon->group_id           = $request->group_id;
            $coupon->term_id            = $request->term_id ?? null;
            $coupon->capacity           = $request->capacity ?? 100;
            $coupon->used_count         = 0;
            $coupon->start_date         = $this->convertPersianToEnglish($request->start_date ?? '');
            $coupon->finish_date        = $this->convertPersianToEnglish($request->finish_date ?? '');
            $coupon->is_active          = $request->boolean('is_active', true);
            $coupon->max_discount       = $request->max_discount;
            $coupon->national_code      = $request->national_code;
            $coupon->enable_installment = $request->boolean('enable_installment', false);
            $coupon->prepayment_amount  = $request->prepayment_amount;
            $coupon->payment_method     = $request->payment_method;
            $coupon->save();

            // Save installment items if provided
            if ($request->has('installment_items') && is_array($request->installment_items)) {
                $this->saveInstallmentItems($coupon, $request->installment_items);
            }

            DB::commit();

            return response()->json([
                'message' => 'بن تخفیف با موفقیت ایجاد شد',
                'data'    => $this->formatCoupon($coupon->fresh(['installmentItems'])),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'خطا در ایجاد بن تخفیف: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Convert Persian/Arabic digits to English digits.
     */
    private function convertPersianToEnglish(string $string): string
    {
        $persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabicDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        return str_replace($arabicDigits, $englishDigits, str_replace($persianDigits, $englishDigits, $string));
    }

    /**
     * Get courses list for autocomplete.
     * GET /coupons/courses
     */
    public function courses(Request $request): JsonResponse
    {
        $query = Course::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%");
            });
        }

        $courses = $query->orderBy('title')
            ->limit($request->get('limit', 20))
            ->get(['id', 'title']);

        return response()->json([
            'data' => $courses,
        ]);
    }
}
