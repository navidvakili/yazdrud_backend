<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Registertut;
use App\Library\Crypt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LearnerClubController extends Controller
{
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
        $registrations = Registertut::with(['course', 'certificate'])
            ->where('enrollment_code', $code)
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
                $hasCertificate = $reg->certificate()->exists() && $reg->certificate_approved;

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
