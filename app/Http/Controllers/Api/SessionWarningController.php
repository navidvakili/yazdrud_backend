<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SessionWarning;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SessionWarningController extends Controller
{
    /**
     * Create a session warning (called by new login attempt, unauthenticated).
     * Validates credentials and creates a pending warning for the existing session.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
            'browser_fingerprint' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validate credentials
        $user = User::where('username', $request->username)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'اطلاعات ورود نادرست است',
            ], 401);
        }

        // Check if user has active sessions
        $activeTokensCount = $user->tokens()->where('name', 'portal-api')->count();
        if ($activeTokensCount === 0) {
            return response()->json([
                'message' => 'هیچ نشست فعالی برای این کاربر وجود ندارد',
                'no_active_session' => true,
            ], 400);
        }

        // Create warning with device info
        $warning = SessionWarning::create([
            'user_id' => $user->getKey(),
            'poll_token' => Str::random(64),
            'status' => 'pending',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'browser_fingerprint' => $request->input('browser_fingerprint'),
        ]);

        return response()->json([
            'message' => 'هشدار نشست موازی ایجاد شد',
            'data' => [
                'warning_id' => $warning->id,
                'poll_token' => $warning->poll_token,
            ],
        ], 201);
    }

    /**
     * Get pending warnings for the current authenticated user (old session polling).
     */
    public function pending(Request $request): JsonResponse
    {
        $warnings = SessionWarning::where('user_id', $request->user()->getKey())
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'created_at', 'ip_address', 'user_agent', 'browser_fingerprint']);

        return response()->json([
            'data' => $warnings,
        ]);
    }

    /**
     * Respond to a warning (accept or reject) - called by the old authenticated session.
     */
    public function respond(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:accepted,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $warning = SessionWarning::where('id', $id)
            ->where('user_id', $request->user()->getKey())
            ->where('status', 'pending')
            ->first();

        if (!$warning) {
            return response()->json([
                'message' => 'هشدار یافت نشد یا قبلاً پاسخ داده شده است',
            ], 404);
        }

        $warning->status = $request->status;
        $warning->save();

        return response()->json([
            'message' => $request->status === 'accepted'
                ? 'نشست موازی تأیید شد'
                : 'نشست موازی رد شد',
        ]);
    }

    /**
     * Check the status of a warning (polled by new session, unauthenticated, uses poll_token).
     */
    public function status(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'poll_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $warning = SessionWarning::where('id', $id)
            ->where('poll_token', $request->poll_token)
            ->first();

        if (!$warning) {
            return response()->json([
                'message' => 'هشدار یافت نشد',
            ], 404);
        }

        return response()->json([
            'data' => [
                'status' => $warning->status,
            ],
        ]);
    }
}
