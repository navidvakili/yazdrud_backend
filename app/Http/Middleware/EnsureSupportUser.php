<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupportUser
{
    /**
     * Only the user with username "support" is allowed.
     * This is a username check, NOT a role check — the super-user bypass in
     * the frontend permissions (admin/support pass all role checks) must not
     * grant admin this access.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->username !== 'support') {
            return response()->json([
                'message' => 'دسترسی غیرمجاز. فقط کاربر support مجاز به این عملیات است.',
            ], 403);
        }

        return $next($request);
    }
}
