<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Enums\FinanceEnum;
use App\Http\Resources\Api\RegistrationResource;
use App\Models\Course;
use App\Models\GatewayTransaction;
use App\Models\Registertut;
use App\Models\RegistertutsPayment;
use App\Services\IranKishService;
use App\Services\SmsService;
use App\Services\EnrollmentCodeGenerator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RegistrationController extends Controller
{
    private IranKishService $iranKish;
    private SmsService $smsService;
    private EnrollmentCodeGenerator $enrollmentCodeGenerator;

    public function __construct(IranKishService $iranKish, SmsService $smsService, EnrollmentCodeGenerator $enrollmentCodeGenerator)
    {
        $this->iranKish = $iranKish;
        $this->smsService = $smsService;
        $this->enrollmentCodeGenerator = $enrollmentCodeGenerator;
    }

    /**
     * Display a listing of registrations.
     *
     * @OA\Get(
     *     path="/api/registrations",
     *     summary="لیست ثبت نام‌ها",
     *     tags={"Registrations"},
     *     @OA\Response(response=200, description="List of registrations")
     * )
     */
    public function index(Request $request)
    {
        $query = Registertut::with(['course', 'payment.transaction']);

        // Filter by course
        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        // Filter by national code
        if ($request->has('kodmeli')) {
            $query->where('kodmeli', $this->convertPersianToEnglish($request->kodmeli));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min((int) $request->get('per_page', 50), 100);
        $registrations = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return RegistrationResource::collection($registrations);
    }

    /**
     * Store a newly created registration.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id'       => 'required|exists:courses,id',
            'fullname'        => 'required|string|max:255',
            'kodmeli'         => 'required|string|size:10',
            'mobile'          => 'required|string|size:11',
            'email'           => 'nullable|email',
            'type'            => 'required|in:1,2',
            'id_edu'          => 'nullable|string',
            'skills'                   => 'nullable|string',
            'motivation'               => 'nullable|string',
            'payment_method'           => 'required|in:online,bank',
            'existing_enrollment_code' => 'nullable|string|size:7',
        ]);

        // Check for existing registration
        $existing = Registertut::where('kodmeli', $this->convertPersianToEnglish($validated['kodmeli']))
            ->where('course_id', $validated['course_id'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'شما قبلاً در این دوره ثبت نام کرده‌اید.',
            ], 422);
        }

        $course = Course::findOrFail($validated['course_id']);

        if (!$course->isAvailable()) {
            return response()->json([
                'message' => 'ظرفیت این دوره تکمیل شده است.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            // ========== BANK RECEIPT payment ==========
            if ($validated['payment_method'] === 'bank') {
                $register = Registertut::create([
                    'kodmeli'        => $this->convertPersianToEnglish($validated['kodmeli']),
                    'course_id'      => $validated['course_id'],
                    'type'           => $validated['type'],
                    'fullname'       => $validated['fullname'],
                    'id_edu'         => $validated['id_edu'] ?? null,
                    'skills'         => $validated['skills'] ?? null,
                    'motivation'     => $validated['motivation'] ?? null,
                    'mobile'         => $this->convertPersianToEnglish($validated['mobile']),
                    'email'          => $validated['email'] ?? null,
                    'payment_method' => $validated['payment_method'],
                    'status'         => 'pending',
                ]);

                $course->increment('registered_count');

                $id = time();
                $gateway = GatewayTransaction::create([
                    'type'          => 'tuts',
                    'port'          => 'BANK_RECEIPT',
                    'username'      => $register->kodmeli,
                    'price'         => (int) $course->amount,
                    'ref_id'        => $id,
                    'tracking_code' => $id,
                    'card_number'   => '0',
                    'status'        => 'PENDING',
                    'ip'            => $request->ip(),
                    'payment_date'  => Carbon::now(),
                ]);

                RegistertutsPayment::create([
                    'transaction_id' => $gateway->id,
                    'register_id'    => $register->id,
                ]);

                // Generate enrollment code inside transaction (reuse existing if provided)
                $enrollmentCode = $validated['existing_enrollment_code'] ?? $this->enrollmentCodeGenerator->generate();
                $register->update(['enrollment_code' => $enrollmentCode]);

                DB::commit();

                // Send SMS outside transaction so API failure doesn't roll back registration
                $this->smsService->sendEnrollmentSms(
                    $register->mobile,
                    $enrollmentCode,
                    $register->fullname,
                    $course->title,
                );

                return response()->json([
                    'message'      => 'ثبت نام شما با موفقیت انجام شد. لطفاً منتظر تایید فیش بانکی باشید.',
                    'registration' => new RegistrationResource($register->fresh(['course', 'payment.transaction'])),
                ], 201);
            }

            // ========== ONLINE payment ==========
            $amount = (int) $course->amount;

            if ($amount > 0) {
                try {
                    $callbackUrl = url('api/registrations/verify');
                    $requestId = uniqid('reg_', true);

                    $result = $this->iranKish->tokenRequest($amount, $callbackUrl, $requestId);
                    $token = $result['token'];

                    // Create gateway transaction (NO registertut record yet)
                    $gateway = GatewayTransaction::create([
                        'type'          => FinanceEnum::TUTS->name,
                        'port'          => 'IRANKISH',
                        'username'      => $this->convertPersianToEnglish($validated['kodmeli']),
                        'price'         => $amount,
                        'ref_id'        => $token,
                        'tracking_code' => '',
                        'card_number'   => '0',
                        'status'        => 'PENDING',
                        'description'   => json_encode([
                            'course_id'                => $validated['course_id'],
                            'kodmeli'                  => $this->convertPersianToEnglish($validated['kodmeli']),
                            'fullname'                 => $validated['fullname'],
                            'type'                     => $validated['type'],
                            'mobile'                   => $this->convertPersianToEnglish($validated['mobile']),
                            'email'                    => $validated['email'] ?? null,
                            'id_edu'                   => $validated['id_edu'] ?? null,
                            'skills'                   => $validated['skills'] ?? null,
                            'motivation'               => $validated['motivation'] ?? null,
                            'payment_method'           => $validated['payment_method'],
                            'existing_enrollment_code' => $validated['existing_enrollment_code'] ?? null,
                        ]),
                        'ip'            => $request->ip(),
                        'payment_date'  => Carbon::now(),
                    ]);

                    DB::commit();

                    return response()->json([
                        'message'        => 'در حال انتقال به درگاه پرداخت...',
                        'redirect_url'   => $this->iranKish->getGatewayRedirectUrl($token),
                        'token'          => $token,
                        'transaction_id' => $gateway->id,
                    ], 200);
                } catch (\Exception $e) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'خطا در اتصال به درگاه پرداخت: ' . $e->getMessage(),
                    ], 500);
                }
            }

            // ========== FREE course ==========
            $register = Registertut::create([
                'kodmeli'        => $this->convertPersianToEnglish($validated['kodmeli']),
                'course_id'      => $validated['course_id'],
                'type'           => $validated['type'],
                'fullname'       => $validated['fullname'],
                'id_edu'         => $validated['id_edu'] ?? null,
                'skills'         => $validated['skills'] ?? null,
                'motivation'     => $validated['motivation'] ?? null,
                'mobile'         => $this->convertPersianToEnglish($validated['mobile']),
                'email'          => $validated['email'] ?? null,
                'payment_method' => $validated['payment_method'],
                'status'         => 'paid',
            ]);

            $course->increment('registered_count');

            $id = time();
            $gateway = GatewayTransaction::create([
                'type'          => 'tuts',
                'port'          => 'FREE',
                'username'      => $register->kodmeli,
                'price'         => 0,
                'ref_id'        => $id,
                'tracking_code' => $id,
                'card_number'   => '0',
                'status'        => 'SUCCEED',
                'ip'            => $request->ip(),
                'payment_date'  => Carbon::now(),
            ]);

            RegistertutsPayment::create([
                'transaction_id' => $gateway->id,
                'register_id'    => $register->id,
            ]);

            // Generate enrollment code inside transaction (reuse existing if provided)
            $enrollmentCode = $validated['existing_enrollment_code'] ?? $this->enrollmentCodeGenerator->generate();
            $register->update(['enrollment_code' => $enrollmentCode]);

            DB::commit();

            // Send SMS outside transaction so API failure doesn't roll back registration
            $this->smsService->sendEnrollmentSms(
                $register->mobile,
                $enrollmentCode,
                $register->fullname,
                $course->title,
            );

            return response()->json([
                'message'      => 'ثبت نام شما با موفقیت انجام شد.',
                'registration' => new RegistrationResource($register->fresh(['course', 'payment.transaction'])),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'خطایی در ثبت نام رخ داد: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified registration.
     */
    public function show($id)
    {
        $registration = Registertut::with(['course', 'payment.transaction'])->findOrFail($id);
        return new RegistrationResource($registration);
    }

    /**
     * Verify online payment after bank callback.
     *
     * The bank redirects the user to this endpoint after payment.
     */
    public function verify(Request $request)
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $redirectPath = $frontendUrl . '/verify-payment';

        try {
            // Retrieve callback parameters from IranKish
            $token = $request->input('token');
            $retrievalReferenceNumber = $request->input('retrievalReferenceNumber');
            $systemTraceAuditNumber = $request->input('systemTraceAuditNumber');
            $responseCode = $request->input('responseCode');

            // Find the gateway transaction by token (ref_id)
            $gatewayTransaction = GatewayTransaction::where('ref_id', $token)->first();

            if (!$gatewayTransaction) {
                $params = http_build_query([
                    'status'  => 'failed',
                    'message' => 'تراکنش یافت نشد.',
                ]);
                return redirect()->away($redirectPath . '?' . $params);
            }

            // Check if payment was successful from callback
            if ($responseCode !== '00') {
                $gatewayTransaction->update(['status' => 'FAILED']);
                $params = http_build_query([
                    'status'  => 'failed',
                    'message' => 'پرداخت ناموفق بود.',
                ]);
                return redirect()->away($redirectPath . '?' . $params);
            }

            // Check if RegistertutsPayment already exists (prevent duplicate)
            $existingPayment = RegistertutsPayment::where('transaction_id', $gatewayTransaction->id)->first();
            if ($existingPayment) {
                // Registration already created, just redirect to success
                $register = Registertut::find($existingPayment->register_id);
                $params = http_build_query([
                    'status'          => 'success',
                    'message'         => 'پرداخت شما با موفقیت انجام شد.',
                    'ref_id'          => $retrievalReferenceNumber,
                    'tracking_code'   => $retrievalReferenceNumber,
                    'registration_id' => $register ? 'SAU-' . str_pad($register->id, 5, '0', STR_PAD_LEFT) : null,
                    'enrollment_code' => $register?->enrollment_code,
                ]);
                return redirect()->away($redirectPath . '?' . $params);
            }

            // Verify payment with IranKish
            try {
                $verifyResult = $this->iranKish->verifyPayment(
                    $token,
                    $retrievalReferenceNumber,
                    $systemTraceAuditNumber
                );
            } catch (\Exception $e) {
                $gatewayTransaction->update(['status' => 'FAILED']);
                $params = http_build_query([
                    'status'  => 'failed',
                    'message' => 'خطا در تایید تراکنش: ' . $e->getMessage(),
                ]);
                return redirect()->away($redirectPath . '?' . $params);
            }

            // Read registration data from description
            $description = $gatewayTransaction->description;
            $regData = $description ? json_decode($description, true) : null;

            if (!$regData || !isset($regData['course_id'], $regData['kodmeli'], $regData['fullname'])) {
                $params = http_build_query([
                    'status'  => 'failed',
                    'message' => 'اطلاعات ثبت نام در این تراکنش یافت نشد.',
                ]);
                return redirect()->away($redirectPath . '?' . $params);
            }

            DB::beginTransaction();

            try {
                // Create the Registertut record NOW, after successful payment
                $register = Registertut::create([
                    'kodmeli'        => $regData['kodmeli'],
                    'course_id'      => $regData['course_id'],
                    'type'           => $regData['type'] ?? '1',
                    'fullname'       => $regData['fullname'],
                    'id_edu'         => $regData['id_edu'] ?? null,
                    'skills'         => $regData['skills'] ?? null,
                    'motivation'     => $regData['motivation'] ?? null,
                    'mobile'         => $regData['mobile'],
                    'email'          => $regData['email'] ?? null,
                    'payment_method' => 'online',
                    'status'         => 'paid',
                ]);

                // Increment course registered count
                Course::where('id', $regData['course_id'])->increment('registered_count');

                // Link payment to registration
                RegistertutsPayment::create([
                    'transaction_id' => $gatewayTransaction->id,
                    'register_id'    => $register->id,
                ]);

                // Update transaction status to SUCCEED
                $gatewayTransaction->update([
                    'status'        => 'SUCCEED',
                    'tracking_code' => $retrievalReferenceNumber,
                    'card_number'   => $request->input('maskedPan', ''),
                ]);

                // Generate enrollment code inside transaction (reuse existing if provided)
                $enrollmentCode = $regData['existing_enrollment_code'] ?? $this->enrollmentCodeGenerator->generate();
                $register->update(['enrollment_code' => $enrollmentCode]);

                DB::commit();

                // Send SMS outside transaction so API failure doesn't roll back registration
                $course = Course::find($regData['course_id']);
                $this->smsService->sendEnrollmentSms(
                    $register->mobile,
                    $enrollmentCode,
                    $register->fullname,
                    $course?->title ?? 'دوره آموزشی',
                );
            } catch (\Exception $e) {
                DB::rollBack();
                $params = http_build_query([
                    'status'  => 'failed',
                    'message' => 'خطا در نهایی‌سازی ثبت نام: ' . $e->getMessage(),
                ]);
                return redirect()->away($redirectPath . '?' . $params);
            }

            // Success — redirect to frontend with payment details
            $params = http_build_query([
                'status'          => 'success',
                'message'         => 'پرداخت شما با موفقیت انجام شد. ثبت نام شما نهایی گردید.',
                'ref_id'          => $retrievalReferenceNumber,
                'tracking_code'   => $retrievalReferenceNumber,
                'registration_id' => 'SAU-' . str_pad($register->id, 5, '0', STR_PAD_LEFT),
                'enrollment_code' => $enrollmentCode,
            ]);
            return redirect()->away($redirectPath . '?' . $params);

        } catch (\Exception $e) {
            Log::error('Payment verification error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $params = http_build_query([
                'status'  => 'failed',
                'message' => 'خطا در پرداخت: ' . $e->getMessage(),
            ]);
            return redirect()->away($redirectPath . '?' . $params);
        }
    }

    /**
     * Lookup registration by tracking code or national ID.
     */
    public function lookup(Request $request)
    {
        $validated = $request->validate([
            'query' => 'required|string',
        ]);

        $query = $validated['query'];

        // Try to find by tracking code (SAU-XXXXX format)
        if (preg_match('/^SAU-(\d+)$/i', $query, $matches)) {
            $id = (int) $matches[1];
            $registration = Registertut::with(['course', 'payment.transaction'])->find($id);
            if ($registration) {
                return new RegistrationResource($registration);
            }
        }

        // Try to find by national code
        $registration = Registertut::with(['course', 'payment.transaction'])
            ->where('kodmeli', $this->convertPersianToEnglish($query))
            ->first();

        if ($registration) {
            return new RegistrationResource($registration);
        }

        return response()->json(['message' => 'ثبت نامی با این مشخصات یافت نشد.'], 404);
    }

    /**
     * Update registration status and notes (admin).
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status'            => 'required|in:pending,approved,rejected,paid',
            'note'              => 'nullable|string',
            'rejection_reason'  => 'nullable|string',
        ]);

        $registration = Registertut::findOrFail($id);
        $registration->status = $validated['status'];

        if (isset($validated['note'])) {
            $registration->note = $validated['note'];
        }

        if ($validated['status'] === 'rejected' && isset($validated['rejection_reason'])) {
            $registration->rejected_receipt = true;
            $registration->rejected_at = Carbon::now();
            $registration->rejection_reason = $validated['rejection_reason'];
        }

        if ($validated['status'] === 'approved' || $validated['status'] === 'paid') {
            $registration->verified_receipt = true;
            $registration->verified_at = Carbon::now();
        }

        $registration->save();

        return new RegistrationResource($registration->fresh(['course', 'payment.transaction']));
    }

    /**
     * Lookup registration by enrollment code.
     *
     * Returns matching registration data so the frontend can auto-fill fields.
     */
    public function lookupByEnrollmentCode(string $code)
    {
        $registration = Registertut::with(['course', 'payment.transaction'])
            ->where('enrollment_code', $code)
            ->first();

        if (!$registration) {
            return response()->json([
                'message' => 'کد فراگیر وارد شده معتبر نیست.',
            ], 404);
        }

        return new RegistrationResource($registration);
    }

    /**
     * Remove the specified registration.
     */
    public function destroy($id)
    {
        $registration = Registertut::findOrFail($id);

        // Decrement course registered count
        Course::where('id', $registration->course_id)
            ->where('registered_count', '>', 0)
            ->decrement('registered_count');

        $registration->delete();

        return response()->json(['message' => 'ثبت نام با موفقیت حذف شد.']);
    }

    private function convertPersianToEnglish($string): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        return str_replace($persian, $english, $string);
    }
}
