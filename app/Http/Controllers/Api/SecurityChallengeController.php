<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Public, unauthenticated CAPTCHA-family challenge generator/verifier used by the
 * form-builder's «فیلد امنیتی» (security field) — image_captcha / numeric_code /
 * image_challenge appearances. Challenges are opaque server-side state (answer,
 * attempt count, expiry) kept in Cache keyed by a random token; the token is the
 * only thing handed to the client. `verify()` gives the public form instant
 * client-side feedback but is NOT the authoritative gate — FormController::submit()
 * re-checks (and consumes) the same cache entry before persisting a submission,
 * exactly like FormShareLink's password check is never trusted from the client.
 */
class SecurityChallengeController extends Controller
{
    private const CACHE_PREFIX = 'security_challenge_';
    private const MIN_EXPIRY = 30;
    private const MAX_EXPIRY = 600;
    private const MIN_ATTEMPTS = 1;
    private const MAX_ATTEMPTS = 10;

    /**
     * Generate a new challenge and return its image (as a data URI) plus the
     * opaque token the client must echo back at verify/submit time.
     * POST /forms/security-challenge/generate
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'           => 'required|in:image_captcha,numeric_code,image_challenge',
            'length'         => 'sometimes|integer|min:3|max:8',
            'case_sensitive' => 'sometimes|boolean',
            'expires_in'     => 'sometimes|integer|min:' . self::MIN_EXPIRY . '|max:' . self::MAX_EXPIRY,
            'max_attempts'   => 'sometimes|integer|min:' . self::MIN_ATTEMPTS . '|max:' . self::MAX_ATTEMPTS,
        ]);

        $length = $validated['length'] ?? 5;
        $caseSensitive = $validated['case_sensitive'] ?? false;
        $ttl = $validated['expires_in'] ?? 120;
        $maxAttempts = $validated['max_attempts'] ?? 4;

        [$answer, $displayText] = $this->buildChallengeText($validated['type'], $length);

        $token = (string) Str::uuid();

        Cache::put(self::CACHE_PREFIX . $token, [
            'answer'         => $caseSensitive ? $answer : mb_strtolower($answer),
            'case_sensitive' => $caseSensitive,
            'attempts'       => 0,
            'max_attempts'   => $maxAttempts,
            'expires_at'     => now()->addSeconds($ttl)->timestamp,
        ], $ttl);

        return response()->json([
            'data' => [
                'token'      => $token,
                'image'      => $this->renderChallengeImage($displayText, $validated['type']),
                'expires_in' => $ttl,
            ],
        ]);
    }

    /**
     * Real-time client-side feedback only (does not consume the token) — the
     * authoritative, single-use check happens in FormController::submit().
     * POST /forms/security-challenge/verify
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'value' => 'required|string|max:20',
        ]);

        $key = self::CACHE_PREFIX . $validated['token'];
        $challenge = Cache::get($key);

        if (!$challenge) {
            return response()->json(['data' => [
                'valid'   => false,
                'expired' => true,
                'message' => 'کد امنیتی منقضی شده است. لطفاً کد جدید دریافت کنید.',
            ]]);
        }

        $submitted = $challenge['case_sensitive'] ? $validated['value'] : mb_strtolower($validated['value']);

        if (hash_equals($challenge['answer'], $submitted)) {
            return response()->json(['data' => ['valid' => true]]);
        }

        $challenge['attempts']++;

        if ($challenge['attempts'] >= $challenge['max_attempts']) {
            Cache::forget($key);

            return response()->json(['data' => [
                'valid'   => false,
                'expired' => true,
                'message' => 'تعداد تلاش‌های مجاز به پایان رسید. لطفاً کد جدید دریافت کنید.',
            ]]);
        }

        $remaining = max(1, $challenge['expires_at'] - now()->timestamp);
        Cache::put($key, $challenge, $remaining);

        $attemptsLeft = $challenge['max_attempts'] - $challenge['attempts'];

        return response()->json(['data' => [
            'valid'         => false,
            'expired'       => false,
            'attempts_left' => $attemptsLeft,
            'message'       => "کد واردشده صحیح نیست. ({$attemptsLeft} تلاش باقی‌مانده)",
        ]]);
    }

    // ==================== HELPERS ====================

    /**
     * Returns [answer, displayText] — identical for image_captcha/numeric_code
     * (the rendered text itself is the answer), but distinct for image_challenge
     * where the image shows a simple arithmetic problem and the answer is its result.
     */
    private function buildChallengeText(string $type, int $length): array
    {
        if ($type === 'numeric_code') {
            $text = '';
            for ($i = 0; $i < $length; $i++) {
                $text .= (string) random_int(0, 9);
            }
            return [$text, $text];
        }

        if ($type === 'image_challenge') {
            $a = random_int(1, 20);
            $b = random_int(1, 9);

            if (random_int(0, 1) === 1) {
                [$a, $b] = [max($a, $b), min($a, $b)];
                return [(string) ($a - $b), "{$a} - {$b} = ?"];
            }

            return [(string) ($a + $b), "{$a} + {$b} = ?"];
        }

        // image_captcha — uppercase letters + digits, ambiguous glyphs (0/O, 1/I/L) excluded
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $text = '';
        for ($i = 0; $i < $length; $i++) {
            $text .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return [$text, $text];
    }

    /**
     * Renders the challenge text onto a noisy PNG using GD, returned as a base64
     * data URI (no need to persist a file for a one-shot, short-lived challenge).
     */
    private function renderChallengeImage(string $displayText, string $type): string
    {
        $width = $type === 'image_challenge' ? 190 : 150;
        $height = 56;

        $image = imagecreatetruecolor($width, $height);
        $bg = imagecolorallocate($image, 245, 247, 250);
        imagefilledrectangle($image, 0, 0, $width, $height, $bg);

        for ($i = 0; $i < 6; $i++) {
            $lineColor = imagecolorallocate($image, random_int(180, 220), random_int(180, 220), random_int(180, 220));
            imageline($image, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $lineColor);
        }

        for ($i = 0; $i < 80; $i++) {
            $dotColor = imagecolorallocate($image, random_int(190, 230), random_int(190, 230), random_int(190, 230));
            imagesetpixel($image, random_int(0, $width - 1), random_int(0, $height - 1), $dotColor);
        }

        $textColor = imagecolorallocate($image, random_int(20, 70), random_int(20, 70), random_int(80, 130));
        $fontPath = resource_path('fonts/Vazir-Regular.ttf');

        if (function_exists('imagettftext') && is_file($fontPath)) {
            $fontSize = $type === 'image_challenge' ? 18 : 22;
            $chars = str_split($displayText);
            $spacing = ($width - 24) / max(count($chars), 1);
            $x = 12;
            foreach ($chars as $char) {
                $angle = random_int(-12, 12);
                $y = (int) ($height / 2 + $fontSize / 2 + random_int(-4, 4));
                imagettftext($image, $fontSize, $angle, (int) $x, $y, $textColor, $fontPath, $char);
                $x += $spacing;
            }
        } else {
            imagestring($image, 5, 10, (int) ($height / 2 - 8), $displayText, $textColor);
        }

        ob_start();
        imagepng($image);
        $raw = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,' . base64_encode($raw);
    }
}
