<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class IranKishService
{
    /**
     * IranKish API v3 endpoints
     */
    private string $tokenUrl = 'https://ikc.shaparak.ir/api/v3/tokenization/make';
    private string $verifyUrl = 'https://ikc.shaparak.ir/api/v3/confirmation/purchase';
    private string $gateUrl = 'https://ikc.shaparak.ir/iuiv3/IPG/Index/';

    private string $terminalId;
    private string $acceptorId;
    private string $password;
    private string $publicKey;

    public function __construct()
    {
        $this->terminalId = config('gateway.irankish.terminalID');
        $this->acceptorId = config('gateway.irankish.acceptorId');
        $this->password = config('gateway.irankish.password');
        $this->publicKey = config('gateway.irankish.pubkey');
    }

    /**
     * Generate authentication envelope using RSA + AES encryption.
     */
    private function generateAuthenticationEnvelope(int $amount): array
    {
        $data = $this->terminalId . $this->password . str_pad($amount, 12, '0', STR_PAD_LEFT) . '00';
        $data = hex2bin($data);
        $AESSecretKey = openssl_random_pseudo_bytes(16);
        $ivlen = openssl_cipher_iv_length($cipher = "AES-128-CBC");
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext_raw = openssl_encrypt($data, $cipher, $AESSecretKey, OPENSSL_RAW_DATA, $iv);
        $hmac = hash('sha256', $ciphertext_raw, true);
        $crypttext = '';

        openssl_public_encrypt($AESSecretKey . $hmac, $crypttext, $this->publicKey);

        return [
            "data" => bin2hex($crypttext),
            "iv" => bin2hex($iv),
        ];
    }

    /**
     * Request a payment token from IranKish.
     *
     * @return array{token: string, responseCode: string, description: string}
     * @throws \Exception
     */
    public function tokenRequest(int $amount, string $callbackUrl, string $requestId = null): array
    {
        $token = $this->generateAuthenticationEnvelope($amount);

        $data = [
            "request" => [
                "acceptorId" => $this->acceptorId,
                "amount" => $amount,
                "billInfo" => null,
                "paymentId" => null,
                "requestId" => $requestId ?? uniqid(),
                "requestTimestamp" => time(),
                "revertUri" => $callbackUrl,
                "terminalId" => $this->terminalId,
                "transactionType" => "Purchase",
                "authenticationEnvelope" => $token,
            ],
            "authenticationEnvelope" => $token,
        ];

        $dataString = json_encode($data);

        $ch = curl_init($this->tokenUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($dataString),
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($result === false || $curlError) {
            Log::error('IranKish tokenRequest failed', ['curl_error' => $curlError]);
            throw new \Exception('خطا در اتصال به درگاه پرداخت: ' . $curlError);
        }

        $response = json_decode($result, true);

        if (!$response || !isset($response['responseCode'])) {
            Log::error('IranKish invalid response', ['response' => $result]);
            throw new \Exception('پاسخ نامعتبر از درگاه پرداخت');
        }

        if ($response['responseCode'] !== '00') {
            $desc = $response['description'] ?? 'خطا در دریافت توکن پرداخت';
            Log::error('IranKish token error', [
                'code' => $response['responseCode'],
                'description' => $desc,
            ]);
            throw new \Exception($desc);
        }

        return [
            'token' => $response['result']['token'],
            'responseCode' => $response['responseCode'],
            'description' => $response['description'] ?? '',
        ];
    }

    /**
     * Get the redirect URL for the payment gateway.
     */
    public function getGatewayRedirectUrl(string $token): string
    {
        return $this->gateUrl . $token;
    }

    /**
     * Verify a payment after the bank callback.
     *
     * @param string $token The token returned from callback
     * @param string $retrievalReferenceNumber The retrieval reference number from callback
     * @param string $systemTraceAuditNumber The system trace audit number from callback
     * @return array{amount: int, responseCode: string, description: string}
     * @throws \Exception
     */
    public function verifyPayment(
        string $token,
        string $retrievalReferenceNumber,
        string $systemTraceAuditNumber
    ): array {
        $fields = [
            "terminalId" => $this->terminalId,
            "retrievalReferenceNumber" => $retrievalReferenceNumber,
            "systemTraceAuditNumber" => $systemTraceAuditNumber,
            "tokenIdentity" => $token,
        ];

        $dataString = json_encode($fields);

        $ch = curl_init($this->verifyUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($dataString),
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($result === false || $curlError) {
            Log::error('IranKish verifyPayment failed', ['curl_error' => $curlError]);
            throw new \Exception('خطا در تایید تراکنش با بانک: ' . $curlError);
        }

        $response = json_decode($result, true);

        if (!$response || !isset($response['responseCode'])) {
            Log::error('IranKish verify invalid response', ['response' => $result]);
            throw new \Exception('پاسخ نامعتبر از بانک');
        }

        if ($response['responseCode'] !== '00') {
            $desc = $response['description'] ?? 'خطا در تایید تراکنش';
            Log::error('IranKish verify error', [
                'code' => $response['responseCode'],
                'description' => $desc,
            ]);
            throw new \Exception($desc);
        }

        return [
            'amount' => $response['result']['amount'],
            'responseCode' => $response['responseCode'],
            'description' => $response['description'] ?? '',
        ];
    }
}
