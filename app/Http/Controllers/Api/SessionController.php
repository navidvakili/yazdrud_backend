<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
{
    /**
     * Get the current user's active sessions (tokens).
     * Includes the current session marked as 'current'.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentTokenId = $user->token()->id;

        $sessions = DB::table('oauth_access_tokens')
            ->where('user_id', $user->getKey())
            ->where('name', 'portal-api')
            ->where('revoked', 0)
            ->where('id', '!=', $currentTokenId) // exclude current from general list
            ->orderBy('created_at', 'desc')
            ->get([
                'id', 'ip_address', 'user_agent', 'browser_fingerprint',
                'created_at', 'updated_at', 'expires_at',
            ]);

        // Format sessions
        $formatted = $sessions->map(function ($session) {
            return $this->formatSession($session, false);
        });

        // Get current session details
        $currentSession = DB::table('oauth_access_tokens')
            ->where('id', $currentTokenId)
            ->first([
                'id', 'ip_address', 'user_agent', 'browser_fingerprint',
                'created_at', 'updated_at', 'expires_at',
            ]);

        $currentFormatted = $currentSession
            ? $this->formatSession($currentSession, true)
            : null;

        return response()->json([
            'data' => [
                'current_session' => $currentFormatted,
                'other_sessions' => $formatted,
            ],
        ]);
    }

    /**
     * Revoke a specific session (token) for the current user.
     * Cannot revoke the current session.
     */
    public function revoke(Request $request, string $tokenId): JsonResponse
    {
        $user = $request->user();
        $currentTokenId = $user->token()->id;

        if ($tokenId === $currentTokenId) {
            return response()->json([
                'message' => 'نمی‌توانید نشست فعلی خود را باطل کنید',
            ], 400);
        }

        $token = DB::table('oauth_access_tokens')
            ->where('id', $tokenId)
            ->where('user_id', $user->getKey())
            ->where('revoked', 0)
            ->first();

        if (!$token) {
            return response()->json([
                'message' => 'نشست مورد نظر یافت نشد',
            ], 404);
        }

        DB::table('oauth_access_tokens')
            ->where('id', $tokenId)
            ->update(['revoked' => 1]);

        return response()->json([
            'message' => 'نشست مورد نظر با موفقیت باطل شد',
        ]);
    }

    /**
     * Admin: Get all active sessions across all users, sorted by username.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only super users (usernames admin/support) or admin-role users can access this
        if (!$user->isSuperUser()) {
            return response()->json([
                'message' => 'شما مجوز دسترسی به این بخش را ندارید',
            ], 403);
        }

        $currentTokenId = $user->token()->id;

        $sessions = DB::table('oauth_access_tokens')
            ->join('users', 'oauth_access_tokens.user_id', '=', 'users.username')
            ->where('oauth_access_tokens.name', 'portal-api')
            ->where('oauth_access_tokens.revoked', 0)
            ->where('users.username', '!=', 'support')
            ->orderBy('users.username')
            ->orderBy('oauth_access_tokens.created_at', 'desc')
            ->get([
                'oauth_access_tokens.id as token_id',
                'oauth_access_tokens.user_id',
                'oauth_access_tokens.ip_address',
                'oauth_access_tokens.user_agent',
                'oauth_access_tokens.browser_fingerprint',
                'oauth_access_tokens.created_at',
                'oauth_access_tokens.updated_at',
                'oauth_access_tokens.expires_at',
                'users.fname',
                'users.lname',
                'users.role',
            ]);

        $formatted = $sessions->map(function ($session) use ($currentTokenId) {
            return [
                'token_id' => $session->token_id,
                'user_id' => $session->user_id,
                'full_name' => trim(($session->fname ?? '') . ' ' . ($session->lname ?? '')),
                'role' => $session->role,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'browser_fingerprint' => $session->browser_fingerprint,
                'login_at' => $session->created_at,
                'updated_at' => $session->updated_at,
                'expires_at' => $session->expires_at,
                'is_current' => $session->token_id === $currentTokenId,
            ];
        });

        return response()->json([
            'data' => $formatted,
        ]);
    }

    /**
     * Admin: Revoke any user's session.
     */
    public function adminRevoke(Request $request, string $tokenId): JsonResponse
    {
        $user = $request->user();

        // Only super users (usernames admin/support) or admin-role users can access this
        if (!$user->isSuperUser() && !$user->hasRole('admin')) {
            return response()->json([
                'message' => 'شما مجوز دسترسی به این بخش را ندارید',
            ], 403);
        }

        $currentTokenId = $user->token()->id;

        if ($tokenId === $currentTokenId) {
            return response()->json([
                'message' => 'نمی‌توانید نشست فعلی خود را باطل کنید. از گزینه خروج استفاده کنید.',
            ], 400);
        }

        $token = DB::table('oauth_access_tokens')
            ->where('id', $tokenId)
            ->where('revoked', 0)
            ->first();

        if (!$token) {
            return response()->json([
                'message' => 'نشست مورد نظر یافت نشد',
            ], 404);
        }

        DB::table('oauth_access_tokens')
            ->where('id', $tokenId)
            ->update(['revoked' => 1]);

        return response()->json([
            'message' => 'نشست مورد نظر با موفقیت باطل شد',
        ]);
    }

    /**
     * Format a session record for API response.
     */
    private function formatSession($session, bool $isCurrent): array
    {
        // Parse user agent for browser and OS info
        $browser = $this->parseUserAgent($session->user_agent ?? '');

        return [
            'token_id' => $session->id,
            'ip_address' => $session->ip_address,
            'user_agent' => $session->user_agent,
            'browser_fingerprint' => $session->browser_fingerprint,
            'browser' => $browser['browser'],
            'platform' => $browser['platform'],
            'login_at' => $session->created_at,
            'updated_at' => $session->updated_at,
            'expires_at' => $session->expires_at,
            'is_current' => $isCurrent,
        ];
    }

    /**
     * Simple user-agent parser.
     */
    private function parseUserAgent(?string $ua): array
    {
        if (empty($ua)) {
            return ['browser' => 'نامشخص', 'platform' => 'نامشخص'];
        }

        $browser = 'نامشخص';
        $platform = 'نامشخص';

        // Detect browser
        if (preg_match('/Edge\/|Edg\//i', $ua)) $browser = 'Microsoft Edge';
        elseif (preg_match('/OPR\/|Opera\//i', $ua)) $browser = 'Opera';
        elseif (preg_match('/Chrome\/|CriOS\//i', $ua)) $browser = 'Google Chrome';
        elseif (preg_match('/Firefox\/|FxiOS\//i', $ua)) $browser = 'Mozilla Firefox';
        elseif (preg_match('/Safari\//i', $ua)) $browser = 'Safari';
        elseif (preg_match('/MSIE|Trident\//i', $ua)) $browser = 'Internet Explorer';

        // Detect platform
        if (preg_match('/Windows NT/i', $ua)) $platform = 'Windows';
        elseif (preg_match('/Mac OS X/i', $ua)) $platform = 'macOS';
        elseif (preg_match('/Linux/i', $ua)) $platform = 'Linux';
        elseif (preg_match('/Android/i', $ua)) $platform = 'Android';
        elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) $platform = 'iOS';
        elseif (preg_match('/CrOS/i', $ua)) $platform = 'ChromeOS';

        return ['browser' => $browser, 'platform' => $platform];
    }
}
