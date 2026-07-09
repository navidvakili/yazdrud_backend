<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TermCoupon;
use App\Models\Registertut;
use App\Models\RegistrationInstallment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VoucherInstallmentController extends Controller
{
    /**
     * Paginated list of all vouchers that have installment plans enabled.
     * GET /installments
     */
    public function index(Request $request): JsonResponse
    {
        $query = TermCoupon::with(['installmentItems'])
            ->where('enable_installment', true)
            ->withCount(['registrations']);

        // Filter by voucher code
        if ($request->has('code')) {
            $query->where('code', 'like', '%' . $request->code . '%');
        }

        // Filter by title
        if ($request->has('title')) {
            $query->where('title', 'like', '%' . $request->title . '%');
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = $request->get('per_page', 15);
        $vouchers = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Transform data
        $vouchers->getCollection()->transform(function ($voucher) {
            $totalPaid = RegistrationInstallment::whereHas('registration', function ($q) use ($voucher) {
                $q->where('coupon_id', $voucher->id);
            })->where('status', 'paid')->count();

            $totalOverdue = RegistrationInstallment::whereHas('registration', function ($q) use ($voucher) {
                $q->where('coupon_id', $voucher->id);
            })->where('status', 'overdue')->count();

            return [
                'id'                 => $voucher->id,
                'title'              => $voucher->title,
                'code'               => $voucher->code,
                'type_discount'      => $voucher->type_discount,
                'value'              => $voucher->value,
                'prepayment_amount'  => $voucher->prepayment_amount,
                'payment_method'     => $voucher->payment_method,
                'is_active'          => $voucher->is_active,
                'total_installment_amount' => $voucher->installmentItems->sum('amount'),
                'installment_items'  => $voucher->installmentItems->map(function ($item) {
                    return [
                        'id'        => $item->id,
                        'title'     => $item->title,
                        'amount'    => $item->amount,
                        'due_date'  => $item->due_date,
                        'sort_order' => $item->sort_order,
                    ];
                }),
                'registrations_count' => $voucher->registrations_count,
                'total_paid_installments' => $totalPaid,
                'total_overdue_installments' => $totalOverdue,
                'created_at'         => $voucher->created_at,
            ];
        });

        return response()->json($vouchers);
    }

    /**
     * List all registrations that have installment plans.
     * GET /installments/registrations
     */
    public function registrations(Request $request): JsonResponse
    {
        $query = Registertut::with([
            'course',
            'coupon.installmentItems',
            'installments',
        ])->whereNotNull('coupon_id')
          ->whereHas('coupon', function ($q) {
              $q->where('enable_installment', true);
          });

        // Filter by learner name
        if ($request->has('fullname')) {
            $query->where('fullname', 'like', '%' . $request->fullname . '%');
        }

        // Filter by national code
        if ($request->has('kodmeli')) {
            $query->where('kodmeli', 'like', '%' . $request->kodmeli . '%');
        }

        // Filter by mobile
        if ($request->has('mobile')) {
            $query->where('mobile', 'like', '%' . $request->mobile . '%');
        }

        // Filter by voucher code
        if ($request->has('coupon_code')) {
            $query->whereHas('coupon', function ($q) use ($request) {
                $q->where('code', 'like', '%' . $request->coupon_code . '%');
            });
        }

        // Filter by installment status
        if ($request->has('installment_status')) {
            $query->whereHas('installments', function ($q) use ($request) {
                $q->where('status', $request->installment_status);
            });
        }

        // Filter by due date range
        if ($request->has('due_date_from')) {
            $query->whereHas('installments', function ($q) use ($request) {
                $q->where('due_date', '>=', $request->due_date_from);
            });
        }
        if ($request->has('due_date_to')) {
            $query->whereHas('installments', function ($q) use ($request) {
                $q->where('due_date', '<=', $request->due_date_to);
            });
        }

        $perPage = $request->get('per_page', 15);
        $registrations = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Transform data
        $registrations->getCollection()->transform(function ($registration) {
            $installments = $registration->installments->map(function ($inst) {
                return [
                    'id'              => $inst->id,
                    'title'           => $inst->title,
                    'amount'          => $inst->amount,
                    'due_date'        => $inst->due_date,
                    'payment_method'  => $inst->payment_method,
                    'status'          => $inst->status,
                    'paid_at'         => $inst->paid_at ? $inst->paid_at->toDateTimeString() : null,
                    'paid_amount'     => $inst->paid_amount,
                    'tracking_number' => $inst->tracking_number,
                    'notes'           => $inst->notes,
                ];
            });

            return [
                'id'                => $registration->id,
                'fullname'          => $registration->fullname,
                'kodmeli'           => $registration->kodmeli,
                'mobile'            => $registration->mobile,
                'course_title'      => $registration->course?->title,
                'course_id'         => $registration->course_id,
                'coupon_id'         => $registration->coupon_id,
                'coupon_code'       => $registration->coupon?->code,
                'coupon_title'      => $registration->coupon?->title,
                'prepayment_amount' => $registration->prepayment_amount,
                'discount_amount'   => $registration->discount_amount,
                'registration_status' => $registration->actual_status,
                'installments'      => $installments,
                'total_paid'        => $installments->where('status', 'paid')->sum('amount'),
                'total_pending'     => $installments->where('status', 'pending')->sum('amount'),
                'total_overdue'     => $installments->where('status', 'overdue')->sum('amount'),
                'created_at'        => $registration->created_at,
            ];
        });

        return response()->json($registrations);
    }

    /**
     * Get installments for a specific registration.
     * GET /installments/registrations/{registerId}
     */
    public function show($registerId): JsonResponse
    {
        $registration = Registertut::with([
            'course',
            'coupon.installmentItems',
            'installments',
        ])->find($registerId);

        if (!$registration) {
            return response()->json(['message' => 'ثبت نام مورد نظر یافت نشد'], 404);
        }

        $installments = $registration->installments->map(function ($inst) {
            return [
                'id'              => $inst->id,
                'voucher_installment_item_id' => $inst->voucher_installment_item_id,
                'title'           => $inst->title,
                'amount'          => $inst->amount,
                'due_date'        => $inst->due_date,
                'payment_method'  => $inst->payment_method,
                'status'          => $inst->status,
                'paid_at'         => $inst->paid_at ? $inst->paid_at->toDateTimeString() : null,
                'paid_amount'     => $inst->paid_amount,
                'tracking_number' => $inst->tracking_number,
                'verified_by'     => $inst->verified_by,
                'notes'           => $inst->notes,
            ];
        });

        $data = [
            'id'                  => $registration->id,
            'fullname'            => $registration->fullname,
            'kodmeli'             => $registration->kodmeli,
            'mobile'              => $registration->mobile,
            'email'               => $registration->email,
            'course_title'        => $registration->course?->title,
            'course_id'           => $registration->course_id,
            'coupon_code'         => $registration->coupon?->code,
            'coupon_title'        => $registration->coupon?->title,
            'prepayment_amount'   => $registration->prepayment_amount,
            'discount_amount'     => $registration->discount_amount,
            'installment_items'   => $registration->coupon?->installmentItems->map(function ($item) {
                return [
                    'id'        => $item->id,
                    'title'     => $item->title,
                    'amount'    => $item->amount,
                    'due_date'  => $item->due_date,
                ];
            }),
            'installments'        => $installments,
            'total_paid'          => $registration->installments->where('status', 'paid')->sum('amount'),
            'total_pending'       => $registration->installments->where('status', 'pending')->sum('amount'),
            'total_overdue'       => $registration->installments->where('status', 'overdue')->sum('amount'),
        ];

        return response()->json($data);
    }

    /**
     * Manually verify an installment as paid (offline/online tracking).
     * POST /installments/{installmentId}/verify
     */
    public function verifyManual(Request $request, $installmentId): JsonResponse
    {
        $installment = RegistrationInstallment::find($installmentId);
        if (!$installment) {
            return response()->json(['message' => 'قسط مورد نظر یافت نشد'], 404);
        }

        if ($installment->status === 'paid') {
            return response()->json(['message' => 'این قسط قبلاً پرداخت شده است.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'tracking_number' => 'nullable|string|max:191',
            'paid_amount'     => 'nullable|integer|min:0',
            'notes'           => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $installment->status = 'paid';
        $installment->paid_at = now();
        $installment->paid_amount = $request->paid_amount ?? $installment->amount;
        if ($request->has('tracking_number')) {
            $installment->tracking_number = $request->tracking_number;
        }
        if ($request->has('notes')) {
            $installment->notes = $request->notes;
        }
        $installment->verified_by = auth()->id();
        $installment->save();

        // Check if all installments are paid → update registration status
        $registration = $installment->registration;
        if ($registration) {
            $totalInstallments = $registration->installments()->count();
            $paidInstallments = $registration->installments()->where('status', 'paid')->count();
            if ($totalInstallments > 0 && $totalInstallments === $paidInstallments) {
                // All installments paid — optionally update registration status
                // (keeping the existing status, but could mark as 'installment_completed')
            }
        }

        return response()->json([
            'message' => 'قسط با موفقیت تأیید شد.',
            'data'    => $installment->fresh(),
        ]);
    }

    /**
     * Revert a paid installment back to pending.
     * POST /installments/{installmentId}/revert
     */
    public function revertPayment($installmentId): JsonResponse
    {
        $installment = RegistrationInstallment::find($installmentId);
        if (!$installment) {
            return response()->json(['message' => 'قسط مورد نظر یافت نشد'], 404);
        }

        if ($installment->status !== 'paid') {
            return response()->json(['message' => 'این قسط هنوز پرداخت نشده است.'], 422);
        }

        $installment->status = 'pending';
        $installment->paid_at = null;
        $installment->paid_amount = null;
        $installment->tracking_number = null;
        $installment->verified_by = null;
        $installment->save();

        return response()->json([
            'message' => 'تأیید قسط لغو شد.',
            'data'    => $installment->fresh(),
        ]);
    }

    /**
     * Update installment notes.
     * PUT /installments/{installmentId}
     */
    public function update(Request $request, $installmentId): JsonResponse
    {
        $installment = RegistrationInstallment::find($installmentId);
        if (!$installment) {
            return response()->json(['message' => 'قسط مورد نظر یافت نشد'], 404);
        }

        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000',
            'due_date' => 'nullable|string|max:191',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->has('notes')) {
            $installment->notes = $request->notes;
        }
        if ($request->has('due_date')) {
            $installment->due_date = $request->due_date;
        }
        $installment->save();

        return response()->json([
            'message' => 'قسط با موفقیت به‌روزرسانی شد.',
            'data'    => $installment->fresh(),
        ]);
    }

    /**
     * Get summary statistics for the dashboard.
     * GET /installments/stats
     */
    public function getStats(): JsonResponse
    {
        $totalVouchers = TermCoupon::where('enable_installment', true)->count();
        $activeVouchers = TermCoupon::where('enable_installment', true)->where('is_active', true)->count();

        $totalRegistrations = Registertut::whereNotNull('coupon_id')
            ->whereHas('coupon', function ($q) {
                $q->where('enable_installment', true);
            })->count();

        $totalInstallments = RegistrationInstallment::count();
        $paidInstallments = RegistrationInstallment::where('status', 'paid')->count();
        $pendingInstallments = RegistrationInstallment::where('status', 'pending')->count();
        $overdueInstallments = RegistrationInstallment::where('status', 'overdue')->count();

        $totalCollected = RegistrationInstallment::where('status', 'paid')->sum('paid_amount');
        $totalExpected = RegistrationInstallment::sum('amount');

        return response()->json([
            'total_vouchers'         => $totalVouchers,
            'active_vouchers'        => $activeVouchers,
            'total_registrations'    => $totalRegistrations,
            'total_installments'     => $totalInstallments,
            'paid_installments'      => $paidInstallments,
            'pending_installments'   => $pendingInstallments,
            'overdue_installments'   => $overdueInstallments,
            'total_collected'        => (int) $totalCollected,
            'total_expected'         => (int) $totalExpected,
            'collection_percentage'  => $totalExpected > 0
                ? round(($totalCollected / $totalExpected) * 100, 1)
                : 0,
        ]);
    }

    /**
     * Bulk update installment due dates.
     * POST /installments/bulk-update-dates
     */
    public function bulkUpdateDates(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'installments'            => 'required|array',
            'installments.*.id'       => 'required|exists:registration_installments,id',
            'installments.*.due_date' => 'required|string|max:191',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updated = 0;
        foreach ($request->installments as $item) {
            $installment = RegistrationInstallment::find($item['id']);
            if ($installment && $installment->status !== 'paid') {
                $installment->due_date = $item['due_date'];
                $installment->save();
                $updated++;
            }
        }

        return response()->json([
            'message' => "{$updated} قسط با موفقیت به‌روزرسانی شد.",
        ]);
    }
}
