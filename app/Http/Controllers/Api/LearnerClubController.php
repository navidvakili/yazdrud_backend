<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Registertut;
use App\Models\RegistrationInstallment;
use App\Library\Crypt;
use App\Services\SmsService;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LearnerClubController extends Controller
{
    private SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Lookup all registrations for a learner by enrollment code.
     * Used by the public "باشگاه فراگیران" page.
     *
     * Returns every registration matching the learner, along with
     * certificate status and a download-ready encrypted register ID.
     */
    public function lookup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|min:2|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'لطفاً کد فراگیر را وارد نمایید.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $code = trim($request->code);

        // Search only by enrollment code (کد فراگیر)
        $registrations = Registertut::with(['course', 'certificate', 'installments', 'coupon.installmentItems'])
            ->where('enrollment_code', $code)
            ->where('refunded', false)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($registrations->isEmpty()) {
            return response()->json([
                'message' => 'کد فراگیر وارد شده در سیستم ثبت‌نام یافت نشد.',
                'data'    => [],
            ], 404);
        }

        // Use the first record to resolve the learner's identity
        $first = $registrations->first();

        $learner = [
            'fullName'       => $first->fullname,
            'nationalId'     => $first->kodmeli,
            'studentId'      => $first->id_edu,
            'mobile'         => $first->mobile,
            'email'          => $first->email,
            'educationLevel' => $this->getEducationLabel($first->type),
        ];

        $courses = $registrations->map(function ($reg) {
            try {
                $hasCertificate = (bool) $reg->certificate_approved;

                // Build installment data if the registration has installments
                $installmentItems = null;
                $enableInstallment = false;
                if ($reg->installments && $reg->installments->isNotEmpty()) {
                    $enableInstallment = true;
                    $installmentItems = $reg->installments->map(function ($inst) {
                        return [
                            'id'              => $inst->id,
                            'title'           => $inst->title,
                            'amount'          => (int) $inst->amount,
                            'due_date'        => $inst->due_date,
                            'status'          => $inst->status,
                            'paid_at'         => $inst->paid_at ? $this->formatDate($inst->paid_at) : null,
                            'paid_amount'     => $inst->paid_amount ? (int) $inst->paid_amount : null,
                            'tracking_number' => $inst->tracking_number,
                            'payment_method'  => $inst->payment_method ?? 'online',
                            'bank_name'       => $inst->bank_name,
                            'deposit_date'    => $inst->deposit_date,
                            'receipt_image'   => $inst->receipt_image,
                        ];
                    })->values()->toArray();

                    // ── Auto-send SMS reminder for due installments ──
                    $todayJalali = Verta::now()->format('Y/m/d');
                    foreach ($reg->installments as $inst) {
                        if (
                            $inst->status === 'pending'
                            && $inst->due_date && $inst->due_date <= $todayJalali
                            && !$inst->reminder_sent_at
                        ) {
                            try {
                                $this->smsService->sendByPattern(
                                    'nzn5zwuedd0kaye',
                                    ['faragir' => $reg->enrollment_code ?? ''],
                                    $reg->mobile,
                                );
                                $inst->update(['reminder_sent_at' => now()]);
                                Log::info('Installment reminder SMS sent', [
                                    'installment_id' => $inst->id,
                                    'register_id'    => $reg->id,
                                    'mobile'         => $reg->mobile,
                                ]);
                            } catch (\Throwable $e) {
                                Log::error('Failed to send installment reminder SMS', [
                                    'installment_id' => $inst->id,
                                    'error'          => $e->getMessage(),
                                ]);
                            }
                        }
                    }
                } elseif ($reg->coupon && $reg->coupon->enable_installment && $reg->coupon->installmentItems && $reg->coupon->installmentItems->isNotEmpty()) {
                    // Coupon has installment enabled — try to match with RegistrationInstallment records
                    $enableInstallment = true;
                    $installmentItems = $reg->coupon->installmentItems->map(function ($item) use ($reg) {
                        // Prefer RegistrationInstallment record (with real ID) if it exists
                        $ri = RegistrationInstallment::where('register_id', $reg->id)
                            ->where('voucher_installment_item_id', $item->id)
                            ->first();
                        if ($ri) {
                            return [
                                'id'              => $ri->id,
                                'title'           => $ri->title,
                                'amount'          => (int) $ri->amount,
                                'due_date'        => $ri->due_date,
                                'status'          => $ri->status,
                                'paid_at'         => $ri->paid_at ? $this->formatDate($ri->paid_at) : null,
                                'paid_amount'     => $ri->paid_amount ? (int) $ri->paid_amount : null,
                                'tracking_number' => $ri->tracking_number,
                                'payment_method'  => $ri->payment_method ?? 'online',
                                'bank_name'       => $ri->bank_name,
                                'deposit_date'    => $ri->deposit_date,
                                'receipt_image'   => $ri->receipt_image,
                            ];
                        }
                        // Fallback: use template item (no RegistrationInstallment yet)
                        return [
                            'id'              => $item->id,
                            'title'           => $item->title,
                            'amount'          => (int) $item->amount,
                            'due_date'        => $item->due_date,
                            'status'          => 'pending',
                            'paid_at'         => null,
                            'paid_amount'     => null,
                            'tracking_number' => null,
                            'payment_method'  => 'online',
                            'bank_name'       => null,
                            'deposit_date'    => null,
                            'receipt_image'   => null,
                        ];
                    })->values()->toArray();
                }

                // ── Calculate discount & actual paid amount ──
                $discountAmount = (int) ($reg->discount_amount ?? 0);
                $actualPaidAmount = (int) ($reg->course?->amount ?? 0);
                if ($discountAmount > 0) {
                    if ($enableInstallment && $installmentItems) {
                        $totalInst = collect($installmentItems)->sum('amount');
                        $actualPaidAmount = max(0, $actualPaidAmount - $discountAmount - $totalInst);
                    } else {
                        $actualPaidAmount = max(0, $actualPaidAmount - $discountAmount);
                    }
                }

                return [
                    'registerId'          => $reg->id,
                    'encryptedRegisterId' => Crypt::encryptor('encrypt', $reg->id),
                    'trackingCode'        => 'SAU-' . str_pad($reg->id, 5, '0', STR_PAD_LEFT),
                    'courseId'            => (string) $reg->course_id,
                    'courseTitle'         => $reg->course?->title ?? 'دوره آموزشی',
                    'coursePrice'         => (int) ($reg->course?->amount ?? 0),
                    'registrationDate'    => $reg->created_at ? $this->formatDate($reg->created_at) : '',
                    'status'              => $reg->actual_status,
                    'paymentMethod'       => $reg->payment_method,
                    'certificateApproved' => $reg->certificate_approved,
                    'hasCertificate'      => $hasCertificate,
                    'certificateNumber'   => $hasCertificate ? $reg->certificate?->certificate_number : null,
                    'enableInstallment'   => $enableInstallment,
                    'installmentItems'    => $installmentItems,
                    'installmentSummary'  => $enableInstallment && $installmentItems
                        ? sprintf(
                            '%d از %d قسط پرداخت شده',
                            collect($installmentItems)->where('status', 'paid')->count(),
                            count($installmentItems)
                        )
                        : null,
                    // ── Discount / coupon fields ──
                    'discountAmount'   => $discountAmount,
                    'actualPaidAmount' => $actualPaidAmount,
                    'couponTitle'      => $reg->coupon ? $reg->coupon->title : null,
                    'couponType'       => $reg->coupon ? $reg->coupon->type_discount : null,
                    'couponValue'      => $reg->coupon ? (int) $reg->coupon->value : null,
                ];
            } catch (\Throwable $e) {
                // Log the error and return a safe fallback for this registration
                logger()->error('LearnerClub: failed to process registration', [
                    'register_id' => $reg->id,
                    'error'       => $e->getMessage(),
                ]);
                return null;
            }
        })->filter()->values();

        return response()->json([
            'message' => 'اطلاعات فراگیر با موفقیت یافت شد.',
            'data'    => [
                'learner' => $learner,
                'courses' => $courses,
            ],
        ]);
    }

    /**
     * Get education level label based on type.
     */
    private function getEducationLabel(?string $type): string
    {
        return match ($type) {
            '1'     => 'دانشجو',
            '2'     => 'فارغ‌التحصیل',
            default => 'آزاد',
        };
    }

    /**
     * Format a date to Persian calendar string.
     */
    private function formatDate($date): string
    {
        if (!$date) return '';
        try {
            return \Hekmatinasser\Verta\Verta::instance($date)->format('Y/m/d');
        } catch (\Throwable $e) {
            return $date->format('Y-m-d');
        }
    }
}
