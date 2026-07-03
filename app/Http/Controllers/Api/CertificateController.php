<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Registertut;
use App\Models\Certificate;
use App\Models\Course;
use App\Library\Crypt;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\SmsService;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CertificateController extends Controller
{
    /**
     * List registrations with certificate info.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Registertut::with(['course', 'certificate'])
            ->where(function ($q) {
                // Only show registrations that are verified/paid
                $q->where('verified_receipt', true)
                  ->orWhere(function ($q2) {
                      $q2->where('payment_method', 'online')
                         ->whereHas('payment.transaction', function ($q3) {
                             $q3->where('status', 'SUCCEED');
                         });
                  });
            });

        // Filter by course
        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        // Filter by certificate approval status
        if ($request->filled('certificate_status')) {
            if ($request->certificate_status === 'approved') {
                $query->where('certificate_approved', true);
            } elseif ($request->certificate_status === 'pending') {
                $query->where('certificate_approved', false);
            }
        }

        // Filter by has certificate
        if ($request->filled('has_certificate')) {
            if ($request->boolean('has_certificate')) {
                $query->whereHas('certificate');
            } else {
                $query->whereDoesntHave('certificate');
            }
        }

        // Search by name or national code
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('fullname', 'like', "%{$search}%")
                  ->orWhere('kodmeli', 'like', "%{$search}%")
                  ->orWhere('mobile', 'like', "%{$search}%");
            });
        }

        $registrations = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => $registrations->map(function ($reg) {
                return $this->formatCertificateRegistration($reg);
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
     * Format a registration with certificate data for API response.
     */
    private function formatCertificateRegistration(Registertut $reg): array
    {
        return [
            'id'                      => $reg->id,
            'kodmeli'                 => $reg->kodmeli,
            'course_id'               => $reg->course_id,
            'course_title'            => $reg->course?->title,
            'fullname'                => $reg->fullname,
            'id_edu'                  => $reg->id_edu,
            'mobile'                  => $reg->mobile,
            'type_text'               => $reg->type_text,
            'certificate_approved'    => (bool) $reg->certificate_approved,
            'certificate_approved_at' => $reg->certificate_approved_at?->format('Y/m/d H:i'),
            'certificate'             => $reg->certificate ? [
                'id'                 => $reg->certificate->id,
                'certificate_number' => $reg->certificate->certificate_number,
                'issued_at'          => $reg->certificate->issued_at?->format('Y/m/d H:i'),
            ] : null,
            'created_at'              => $reg->created_at?->format('Y/m/d H:i'),
        ];
    }

    /**
     * Approve a registration for certificate issuance.
     */
    public function approve($registerId): JsonResponse
    {
        $registerId = Crypt::encryptor('decrypt', $registerId);
        $registration = Registertut::with('course')->findOrFail($registerId);

        $registration->certificate_approved     = true;
        $registration->certificate_approved_at = Carbon::now();
        $registration->certificate_approved_by = Auth::user()->username ?? Auth::id();
        $registration->save();

        // Send SMS notification to learner
        try {
            $smsService = app(SmsService::class);
            $smsService->sendByPattern(
                '0wswa3ctz2zxn2z',
                [
                    'term'    => $registration->course?->title ?? 'دوره',
                    'faragir' => $registration->enrollment_code ?? (string) $registration->id,
                ],
                $registration->mobile
            );
        } catch (\Exception $e) {
            Log::error('ارسال پیامک تایید گواهی ناموفق بود.', [
                'register_id' => $registration->id,
                'error'       => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'ثبت‌نام برای صدور گواهی تایید شد.',
            'data'    => $this->formatCertificateRegistration($registration),
        ]);
    }

    /**
     * Remove certificate approval from a registration.
     */
    public function reject($registerId): JsonResponse
    {
        $registerId = Crypt::encryptor('decrypt', $registerId);
        $registration = Registertut::findOrFail($registerId);

        $registration->certificate_approved     = false;
        $registration->certificate_approved_at = null;
        $registration->certificate_approved_by = null;
        $registration->save();

        // If a certificate was already issued, delete it
        if ($registration->certificate) {
            $registration->certificate->delete();
        }

        return response()->json([
            'message' => 'تایید صدور گواهی لغو شد.',
            'data'    => $this->formatCertificateRegistration($registration),
        ]);
    }

    /**
     * Approve all registrations of a course for certificate issuance (bulk).
     */
    public function approveAll(Request $request): JsonResponse
    {
        $courseId = $request->input('course_id');
        if (!$courseId) {
            return response()->json(['message' => 'لطفاً یک دوره را انتخاب کنید.'], 422);
        }

        $registrations = Registertut::with('course')
            ->where('course_id', $courseId)
            ->where(function ($q) {
                $q->where('verified_receipt', true)
                  ->orWhere(function ($q2) {
                      $q2->where('payment_method', 'online')
                         ->whereHas('payment.transaction', function ($q3) {
                             $q3->where('status', 'SUCCEED');
                         });
                  });
            })
            ->whereDoesntHave('certificate')
            ->where('certificate_approved', false)
            ->get();

        $count = 0;
        foreach ($registrations as $registration) {
            $registration->certificate_approved     = true;
            $registration->certificate_approved_at = Carbon::now();
            $registration->certificate_approved_by = Auth::user()->username ?? Auth::id();
            $registration->save();
            $count++;

            // Send SMS notification to each learner
            try {
                $smsService = app(SmsService::class);
                $smsService->sendByPattern(
                    '0wswa3ctz2zxn2z',
                    [
                        'term'    => $registration->course?->title ?? 'دوره',
                        'faragir' => $registration->enrollment_code ?? (string) $registration->id,
                    ],
                    $registration->mobile
                );
            } catch (\Exception $e) {
                Log::error('ارسال پیامک تایید گواهی ناموفق بود.', [
                    'register_id' => $registration->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => "تعداد {$count} ثبت‌نام برای صدور گواهی تایید شدند.",
        ]);
    }

    /**
     * Generate and download a single certificate PDF for a registration.
     */
    public function generate($registerId)
    {
        $registerId = Crypt::encryptor('decrypt', $registerId);
        $registration = Registertut::with(['course', 'certificate'])->findOrFail($registerId);

        // Validate eligibility: must be approved by admin
        if (!$registration->certificate_approved) {
            return response()->json(['message' => 'این ثبت‌نام توسط مدیر تایید نشده است. لطفاً ابتدا آن را تایید کنید.'], 422);
        }

        DB::beginTransaction();
        try {
            // Find or create certificate record
            $certificate = $registration->certificate;
            if (!$certificate) {
                $certificate = Certificate::create([
                    'register_id'        => $registration->id,
                    'certificate_number' => Certificate::generateCertificateNumber($registration->course_id, $registration->id),
                    'issued_at'          => Carbon::now(),
                ]);
            }

            // Build PDF content
            $data = $this->buildCertificateData($registration, $certificate);

            $config = [
                'orientation'    => 'L',
                'format'         => 'A4-L',
                'mode'           => 'utf-8',
                'default_font'   => 'btitr',
                'margin_left'    => 0,
                'margin_right'   => 0,
                'margin_top'     => 0,
                'margin_bottom'  => 0,
            ];
            $pdf = LaravelMpdf::loadView('certificates.pdf', $data, [], $config);

            DB::commit();

            $filename = 'certificate_' . $certificate->certificate_number . '_' . $registration->fullname . '.pdf';
            return $pdf->download($filename);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'خطا در صدور گواهی: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Preview a certificate in the browser.
     */
    public function preview($registerId)
    {
        $registerId = Crypt::encryptor('decrypt', $registerId);
        $registration = Registertut::with(['course', 'certificate'])->findOrFail($registerId);

        if (!$registration->certificate_approved) {
            return response()->json(['message' => 'این ثبت‌نام توسط مدیر تایید نشده است.'], 422);
        }

        DB::beginTransaction();
        try {
            $certificate = $registration->certificate;
            if (!$certificate) {
                $certificate = Certificate::create([
                    'register_id'        => $registration->id,
                    'certificate_number' => Certificate::generateCertificateNumber($registration->course_id, $registration->id),
                    'issued_at'          => Carbon::now(),
                ]);
            }

            $data = $this->buildCertificateData($registration, $certificate);

            $config = [
                'orientation'    => 'L',
                'format'         => 'A4-L',
                'mode'           => 'utf-8',
                'default_font'   => 'btitr',
                'margin_left'    => 0,
                'margin_right'   => 0,
                'margin_top'     => 0,
                'margin_bottom'  => 0,
            ];
            $pdf = LaravelMpdf::loadView('certificates.pdf', $data, [], $config);

            DB::commit();

            $pdfContent = $pdf->output();

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="certificate_' . $certificate->certificate_number . '.pdf"',
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => '*',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'خطا در بارگذاری گواهی: ' . $e->getMessage()], 500);
        }
    }


    /**
     * Build certificate data array for the PDF view.
     */
    private function buildCertificateData(Registertut $registration, Certificate $certificate): array
    {
        $course = $registration->course;

        // Format date in Persian
        $now = Carbon::now();
        $jalaliMonths = [
            'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
            'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند',
        ];

        // Convert course start_date to Jalali month/year
        $courseMonthYear = '—';
        if ($course && $course->start_date) {
            try {
                // start_date is stored as a Shamsi string (e.g. "1405-01-01")
                $parts = explode('-', $course->start_date);
                if (count($parts) >= 2) {
                    $year = (int) $parts[0];
                    $month = (int) $parts[1];
                    $monthIndex = $month - 1;
                    if ($monthIndex >= 0 && $monthIndex < 12) {
                        $courseMonthYear = $jalaliMonths[$monthIndex] . ' ' . $year;
                    }
                }
            } catch (\Exception $e) {
                $courseMonthYear = str_replace('-', '/', $course->start_date);
            }
        }

        // Issued date in Jalali format
        $gy = (int) $now->format('Y');
        $gm = (int) $now->format('m');
        $gd = (int) $now->format('j');
        $jy = $gy - 621;
        $issuedDateWords = $gd . ' ' . $jalaliMonths[$gm - 1] . ' ' . $jy;

        $verificationUrl = route('certificates.verify', Crypt::encryptor('encrypt', $certificate->certificate_number));

        return [
            'certificate_number' => $certificate->certificate_number,
            'fullname'           => $registration->fullname,
            'national_code'      => $registration->kodmeli,
            'course_title'       => $course->title ?? '—',
            'course_duration'    => $course->duration ?? '—',
            'instructor'         => $course->instructor ?? '—',
            // Dates are stored as Shamsi strings (e.g. "1405-01-01")
            'start_date'         => $course->start_date ? str_replace('-', '/', $course->start_date) : '—',
            'end_date'           => $course->end_date ? str_replace('-', '/', $course->end_date) : '—',
            'course_month_year'  => $courseMonthYear,
            'issued_date'        => $jy . '/' . str_pad($gm, 2, '0', STR_PAD_LEFT) . '/' . str_pad($gd, 2, '0', STR_PAD_LEFT),
            'issued_date_words'  => $issuedDateWords,
            'tracking_code'      => 'SAU-' . $registration->id,
            'verification_url'   => $verificationUrl,
            'qrCode'             => $this->generateQrCode($verificationUrl),
        ];
    }

    /**
     * Generate QR code as base64 PNG (for mpdf compatibility).
     */
    private function generateQrCode(string $content): string
    {
        return QrCode::size(80)
                     ->margin(1)
                     ->generate($content);
    }

    /**
     * Download all certificates for approved registrations as a ZIP file.
     */
    public function downloadAll(Request $request)
    {
        $query = Registertut::with(['course', 'certificate'])
            ->where('certificate_approved', true);

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        $registrations = $query->get();

        if ($registrations->isEmpty()) {
            return response()->json(['message' => 'هیچ ثبت‌نام تایید شده‌ای برای صدور گواهی یافت نشد.'], 404);
        }

        // Create ZIP file
        $zipFileName = 'certificates_' . Carbon::now()->format('Ymd_His') . '.zip';
        $zipPath = storage_path('app/temp/' . $zipFileName);

        if (!is_dir(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
            return response()->json(['message' => 'خطا در ایجاد فایل فشرده.'], 500);
        }

        $count = 0;
        foreach ($registrations as $registration) {
            if (!$registration->certificate_approved) {
                continue;
            }

            DB::beginTransaction();
            try {
                $certificate = $registration->certificate;
                if (!$certificate) {
                    $certificate = Certificate::create([
                        'register_id'        => $registration->id,
                        'certificate_number' => Certificate::generateCertificateNumber($registration->course_id, $registration->id),
                        'issued_at'          => Carbon::now(),
                    ]);
                }

                $data = $this->buildCertificateData($registration, $certificate);

                $config = [
                    'orientation'    => 'L',
                    'format'         => 'A4-L',
                    'mode'           => 'utf-8',
                    'default_font'   => 'btitr',
                    'margin_left'    => 0,
                    'margin_right'   => 0,
                    'margin_top'     => 0,
                    'margin_bottom'  => 0,
                ];
                $pdf = LaravelMpdf::loadView('certificates.pdf', $data, [], $config);
                $pdfContent = $pdf->output();

                $filename = $certificate->certificate_number . '_' . $registration->fullname . '.pdf';
                $zip->addFromString($filename, $pdfContent);

                DB::commit();
                $count++;
            } catch (\Exception $e) {
                DB::rollBack();
                continue;
            }
        }

        $zip->close();

        if ($count === 0) {
            unlink($zipPath);
            return response()->json(['message' => 'هیچ گواهی‌ای صادر نشد.'], 404);
        }

        return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
    }

    /**
     * Verify a certificate by its encrypted number.
     * This route is public (no auth required).
     */
    public function verify($encryptedNumber)
    {
        $certificateNumber = Crypt::encryptor('decrypt', $encryptedNumber);

        if (!$certificateNumber) {
            return response()->json([
                'valid'   => false,
                'message' => 'شماره گواهی نامعتبر است.',
            ]);
        }

        $certificate = Certificate::where('certificate_number', $certificateNumber)
            ->with('registration.course')
            ->first();

        if (!$certificate) {
            return response()->json([
                'valid'       => false,
                'message'     => 'گواهی با این شماره یافت نشد.',
                'certificate' => null,
            ]);
        }

        $registration = $certificate->registration;
        $course = $registration->course ?? null;

        return response()->json([
            'valid'       => true,
            'message'     => 'این گواهی معتبر می‌باشد.',
            'certificate' => [
                'certificate_number' => $certificate->certificate_number,
                'issued_at'          => $certificate->issued_at?->format('Y/m/d'),
                'fullname'           => $registration->fullname,
                'national_code'      => $registration->kodmeli,
                'course_title'       => $course->title ?? null,
                'course_duration'    => $course->duration ?? null,
            ],
        ]);
    }
}
