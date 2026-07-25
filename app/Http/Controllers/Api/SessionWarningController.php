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

    /**
     * Login after warning accepted — creates a new token WITHOUT revoking old ones.
     * Called by the new session (Browser 2) after the old session (Browser 1) has accepted.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'warning_id' => 'required|integer',
            'poll_token' => 'required|string',
            'browser_fingerprint' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Find the warning and verify poll_token
        $warning = SessionWarning::where('id', $request->warning_id)
            ->where('poll_token', $request->poll_token)
            ->first();

        if (!$warning) {
            return response()->json([
                'message' => 'هشدار یافت نشد',
            ], 404);
        }

        if ($warning->status !== 'accepted') {
            return response()->json([
                'message' => 'هشدار هنوز تأیید نشده است',
                'errors' => ['warning' => ['وضعیت هشدار "' . $warning->status . '" است و نیاز به تأیید دارد.']],
            ], 409);
        }

        // Get the user
        $user = User::where('username', $warning->user_id)->first();
        if (!$user) {
            return response()->json([
                'message' => 'کاربر یافت نشد',
            ], 404);
        }

        // Create new token — WITHOUT revoking old ones
        $tokenResult = $user->createToken('portal-api');
        $token = $tokenResult->accessToken;

        // Store device info on the token record
        $tokenId = $tokenResult->token->id;
        if ($tokenId) {
            \Illuminate\Support\Facades\DB::table('oauth_access_tokens')
                ->where('id', $tokenId)
                ->update([
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'browser_fingerprint' => $request->input('browser_fingerprint'),
                ]);
        }

        return response()->json([
            'message' => 'ورود موفقیت‌آمیز بود',
            'data' => [
                'user' => $this->formatUser($user),
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Format user data for response (mirrors AuthController::formatUser).
     */
    private function formatUser(User $user): array
    {
        return [
            'username' => $user->username,
            'fname' => $user->fname,
            'lname' => $user->lname,
            'full_name' => $user->getName(),
            'kodmeli' => $user->kodmeli,
            'mobile' => $user->mobile,
            'email' => $user->email,
            'role' => $user->role,
            'roles' => $user->roles,
            'sign' => $user->sign,
            'theme' => $user->theme,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
