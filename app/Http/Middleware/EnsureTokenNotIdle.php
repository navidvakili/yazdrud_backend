<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side idle timeout for Passport personal-access tokens — independent
 * of Passport's own absolute expires_at (see AppServiceProvider). Must run
 * AFTER `auth:api` in the route group so $request->user()->token() is
 * already resolved.
 *
 * `last_used_at` on oauth_access_tokens is the single source of truth for
 * idle detection (not Passport's own `updated_at`, whose write behavior on
 * token validation isn't something this app should depend on).
 */
class EnsureTokenNotIdle
{
    private const IDLE_TIMEOUT_MINUTES = 30;

    /** Only actually write last_used_at at most this often per token, to avoid
     *  a MySQL write on every single authenticated API request. */
    private const TOUCH_THROTTLE_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->token();

        if (!$token) {
            return $next($request);
        }

        $tokenId = $token->id;

        $lastUsedAt = DB::table('oauth_access_tokens')
            ->where('id', $tokenId)
            ->value('last_used_at');

        // Carbon::lt()/subMinutes() comparison instead of diffInMinutes(): Carbon 3
        // made diffInMinutes() return a SIGNED value by default (a past
        // timestamp yields a negative number), so a naive "> 30" check would
        // silently never trip. Comparing two absolute points in time avoids
        // depending on diff-sign semantics entirely.
        if ($lastUsedAt !== null && \Carbon\Carbon::parse($lastUsedAt)->lt(now()->subMinutes(self::IDLE_TIMEOUT_MINUTES))) {
            DB::table('oauth_access_tokens')
                ->where('id', $tokenId)
                ->update(['revoked' => 1]);

            return response()->json([
                'message' => 'به دلیل عدم فعالیت طولانی، نشست شما پایان یافت. لطفاً دوباره وارد شوید.',
            ], 401);
        }

        // Atomic, throttled touch — a single conditional UPDATE, not a
        // read-then-write in PHP, so concurrent requests for the same token
        // can't race each other into redundant writes.
        DB::table('oauth_access_tokens')
            ->where('id', $tokenId)
            ->where(function ($q) {
                $q->whereNull('last_used_at')
                    ->orWhere('last_used_at', '<', now()->subSeconds(self::TOUCH_THROTTLE_SECONDS));
            })
            ->update(['last_used_at' => now()]);

        return $next($request);
    }
}
