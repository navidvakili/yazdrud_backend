<?php

namespace App\Services;

use IPPanel\Client;
use IPPanel\Errors\Error;
use IPPanel\Errors\HttpException;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private string $apiKey;
    private string $senderNumber;
    private ?Client $client = null;

    public function __construct()
    {
        $this->apiKey = config('services.ippanel.api_key');
        $this->senderNumber = config('services.ippanel.sender_number');
    }

    /**
     * Get the IPPanel client instance.
     */
    private function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = new Client($this->apiKey);
        }
        return $this->client;
    }

    /**
     * Send an SMS using a pre-defined pattern (template).
     *
     * @param string $patternCode The pattern code from IPPanel panel
     * @param array $params Key-value pairs for pattern variables
     * @param string $recipient The recipient mobile number
     * @return bool
     */
    public function sendByPattern(string $patternCode, array $params, string $recipient): bool
    {
        try {
            $client = $this->getClient();

            $client->sendPattern(
                $patternCode,                          // pattern code
                $this->senderNumber,                   // originator
                $recipient,                            // recipient
                $params                                // pattern values
            );

            return true;
        } catch (Error $e) {
            Log::error('SMS pattern send failed (IPPanel error)', [
                'pattern_code' => $patternCode,
                'recipient' => $recipient,
                'error' => $e->unwrap(),
                'code' => $e->getCode(),
            ]);
            return false;
        } catch (HttpException $e) {
            Log::error('SMS pattern send failed (HTTP error)', [
                'pattern_code' => $patternCode,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('SMS pattern send failed (Unexpected)', [
                'pattern_code' => $patternCode,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send a verification code for password reset.
     *
     * @param string $mobile Recipient mobile number
     * @param string $code The verification code
     * @return bool
     */
    public function sendVerificationCode(string $mobile, string $code): bool
    {
        $patternCode = config('services.ippanel.verify_pattern', 'b4gofrp80wrccix');

        return $this->sendByPattern($patternCode, [
            'code' => $code,
        ], $mobile);
    }

    /**
     * Send an enrollment code SMS to a successfully registered student.
     *
     * The IPPanel pattern should accept variables like {name}, {course}, {code}.
     * Falls back silently if the pattern is not configured.
     *
     * @param string $mobile Recipient mobile number
     * @param string $enrollmentCode The 7-digit enrollment code
     * @param string $fullName The student's full name
     * @param string $courseTitle The course title
     * @return bool
     */
    public function sendEnrollmentSms(string $mobile, string $enrollmentCode, string $fullName, string $courseTitle): bool
    {
        $patternCode = config('services.ippanel.enrollment_pattern');

        if (empty($patternCode)) {
            Log::info('Enrollment SMS skipped — no enrollment_pattern configured.', [
                'mobile' => $mobile,
                'code' => $enrollmentCode,
            ]);
            return false;
        }

        return $this->sendByPattern($patternCode, [
            'name'   => $fullName,
            'course' => $courseTitle,
            'code'   => $enrollmentCode,
        ], $mobile);
    }
}
