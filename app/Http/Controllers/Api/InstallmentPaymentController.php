<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GatewayTransaction;
use App\Models\RegistrationInstallment;
use App\Models\Registertut;
use App\Services\IranKishService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Controller for public installment payment flow.
 *
 * Learners can pay individual installments via the Learner Club dashboard.
 * Each installment is sent to IranKish gateway, then verified on callback.
 */
class InstallmentPaymentController extends Controller
{
    private IranKishService $iranKish;

    public function __construct(IranKishService $iranKish)
    {
        $this->iranKish = $iranKish;
    }

    /**
     * Initiate payment for a specific registration installment.
     *
     * POST /api/public/installments/{installmentId}/pay
     *
     * @param int $installmentId
     * @param Request $request
     * @return JsonResponse
     */
    public function pay(int $installmentId, Request $request): JsonResponse
    {
        // Try to find by RegistrationInstallment::id first, then by voucher_installment_item_id
        $installment = RegistrationInstallment::with('registration')->find($installmentId);

        if (!$installment) {
            // Fallback: the frontend may have sent a VoucherInstallmentItem::id (from legacy fallback path)
            $installment = RegistrationInstallment::with('registration')
                ->where('voucher_installment_item_id', $installmentId)
                ->first();
        }

        if (!$installment) {
            return response()->json(['message' => 'قسط مورد نظر یافت نشد.'], 404);
        }

        // Only pending installments can be paid
        if ($installment->status !== 'pending') {
            return response()->json(['message' => 'این قسط قبلاً پرداخت شده یا وضعیت نامعتبر دارد.'], 422);
        }

        $register = $installment->registration;
        if (!$register) {
            return response()->json(['message' => 'ثبت‌نام مرتبط با این قسط یافت نشد.'], 404);
        }

        $amount = (int) $installment->amount;

        if ($amount <= 0) {
            return response()->json(['message' => 'مبلغ قسط نامعتبر است.'], 422);
        }

        // ── Offline (bank) payment ──
        if ($installment->payment_method === 'offline') {
            $trackingNumber = $request->input('tracking_number');
            $bankName = $request->input('bank_name');
            $depositDate = $request->input('deposit_date');
            $receiptImage = null;

            if (!$trackingNumber || !$bankName || !$depositDate) {
                return response()->json([
                    'message' => 'لطفاً کد پیگیری، نام بانک و تاریخ واریز را وارد نمایید.',
                ], 422);
            }

            // Handle receipt image upload
            if ($request->hasFile('receipt_image')) {
                $receiptImage = $request->file('receipt_image')->store('installments', 'public');
            }

            $installment->update([
                'tracking_number' => $trackingNumber,
                'bank_name'       => $bankName,
                'deposit_date'    => $depositDate,
                'receipt_image'   => $receiptImage,
                'status'          => 'pending',
            ]);

            return response()->json([
                'message'     => 'اطلاعات پرداخت قسط با موفقیت ثبت شد و پس از تایید مدیر، نهایی خواهد شد.',
                'installment' => [
                    'id'     => $installment->id,
                    'title'  => $installment->title,
                    'status' => 'pending',
                ],
            ], 200);
        }

        // ── IranKish gateway ──
        try {
            $callbackUrl = url('api/public/installments/pay/verify');
            $requestId = uniqid();

            $result = $this->iranKish->tokenRequest($amount, $callbackUrl, $requestId);
            $token = $result['token'];

            // Create gateway transaction with INIT status
            $gateway = GatewayTransaction::create([
                'type'          => 'installment',
                'port'          => 'IRANKISH',
                'username'      => $register->kodmeli,
                'price'         => $amount,
                'ref_id'        => $token,
                'tracking_code' => '',
                'card_number'   => '0',
                'status'        => 'INIT',
                'description'   => json_encode([
                    'installment_id' => $installment->id,
                    'register_id'    => $register->id,
                ]),
                'ip'           => $request->ip(),
                'payment_date' => Carbon::now(),
            ]);

            // Link the installment to this transaction
            $installment->update([
                'gateway_transaction_id' => $gateway->id,
            ]);

            return response()->json([
                'message'      => 'در حال انتقال به درگاه پرداخت...',
                'redirect_url' => 'https://ikc.shaparak.ir/iuiv3/IPG/Index',
                'token'        => $token,
                'installment'  => [
                    'id'     => $installment->id,
                    'title'  => $installment->title,
                    'amount' => $amount,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Installment payment token request failed', [
                'installment_id' => $installment->id,
                'error'          => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'خطا در اتصال به درگاه پرداخت: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify installment payment after IranKish callback.
     *
     * ANY /api/public/installments/pay/verify
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function verify(Request $request): RedirectResponse
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $redirectPath = $frontendUrl . '/club';

        try {
            $token = $request->input('token');
            $retrievalReferenceNumber = $request->input('retrievalReferenceNumber');
            $systemTraceAuditNumber = $request->input('systemTraceAuditNumber');
            $responseCode = $request->input('responseCode');

            // Find the gateway transaction by token
            $gatewayTransaction = GatewayTransaction::where('ref_id', $token)->first();

            if (!$gatewayTransaction) {
                $params = http_build_query([
                    'status'  => 'failed',
                    'message' => 'تراکنش پرداخت قسط یافت نشد.',
                ]);
                return redirect()->away($redirectPath . '?' . $params);
            }

            // Parse description to get installment_id
            $description = json_decode($gatewayTransaction->description, true);
            $installmentId = $description['installment_id'] ?? null;

            if (!$installmentId) {
                $gatewayTransaction->update(['status' => 'FAILED']);
                $params = http_build_query([
                    'status'  => 'failed',
                    'message' => 'اطلاعات قسط در این تراکنش یافت نشد.',
                ]);
                return redirect()->away($redirectPath . '?' . $params);
            }

            $installment = RegistrationInstallment::find($installmentId);

            if (!$installment) {
                $gatewayTransaction->update(['status' => 'FAILED']);
                $params = http_build_query([
                    'status'  => 'failed',
                    'message' => 'قسط مورد نظر یافت نشد.',
                ]);
                return redirect()->away($redirectPath . '?' . $params);
            }

            // Check if payment failed from callback
            if ($responseCode !== '00') {
                $gatewayTransaction->update(['status' => 'FAILED']);
                $params = http_build_query([
                    'status'  => 'failed',
                    'message' => 'پرداخت قسط ناموفق بود.',
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
                    'message' => 'خطا در تایید تراکنش قسط: ' . $e->getMessage(),
                ]);
                return redirect()->away($redirectPath . '?' . $params);
            }

            // Mark installment as paid
            $installment->update([
                'status'          => 'paid',
                'paid_at'         => now(),
                'paid_amount'     => (int) $gatewayTransaction->price,
                'tracking_number' => $retrievalReferenceNumber,
            ]);

            // Update gateway transaction
            $gatewayTransaction->update([
                'status'        => 'SUCCEED',
                'tracking_code' => $retrievalReferenceNumber,
                'card_number'   => $request->input('maskedPan', ''),
            ]);

            $params = http_build_query([
                'status'       => 'success',
                'message'      => 'قسط با موفقیت پرداخت شد.',
                'installment'  => $installment->title,
                'tracking'     => $retrievalReferenceNumber,
            ]);

            return redirect()->away($redirectPath . '?' . $params);
        } catch (\Exception $e) {
            Log::error('Installment payment verify error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            $params = http_build_query([
                'status'  => 'failed',
                'message' => 'خطا در پرداخت قسط: ' . $e->getMessage(),
            ]);
            return redirect()->away($redirectPath . '?' . $params);
        }
    }
}
