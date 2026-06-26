<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login user and create token.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required_without:email|string',
            'email' => 'required_without:username|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Find user by username or email
        $user = null;
        if ($request->filled('username')) {
            $user = User::where('username', $request->username)->first();
        } elseif ($request->filled('email')) {
            $user = User::where('email', $request->email)->first();
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'اطلاعات ورود نادرست است',
                'errors' => [
                    'username' => ['نام کاربری یا رمز عبور اشتباه است'],
                ],
            ], 401);
        }

        // Check for existing active sessions (concurrent login detection)
        $activeTokensCount = $user->tokens()->where('name', 'portal-api')->count();
        if ($activeTokensCount > 0 && !$request->boolean('force')) {
            return response()->json([
                'message' => 'این کاربر در حال حاضر در یک دستگاه دیگر وارد شده است',
                'has_active_session' => true,
                'errors' => [
                    'session' => ['این کاربر در حال حاضر در دستگاه دیگری وارد سیستم شده است. اگر ادامه دهید، نشست (session) قبلی باطل خواهد شد.'],
                ],
            ], 409);
        }

        // Revoke old tokens
        $user->tokens()->where('name', 'portal-api')->delete();

        // Create new token
        $tokenResult = $user->createToken('portal-api');
        $token = $tokenResult->accessToken;

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
     * Register a new user.
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255|unique:users',
            'fname' => 'required|string|max:255',
            'lname' => 'required|string|max:255',
            'kodmeli' => 'required|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'username' => $request->username,
            'fname' => $request->fname,
            'lname' => $request->lname,
            'kodmeli' => $request->kodmeli,
            'mobile' => $request->mobile,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'student',
        ]);

        $token = $user->createToken('portal-api')->accessToken;

        return response()->json([
            'message' => 'ثبت‌نام موفقیت‌آمیز بود',
            'data' => [
                'user' => $this->formatUser($user),
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    /**
     * Get the authenticated user.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user()->load(['student', 'teacher', 'rolesRelation']);

        return response()->json([
            'data' => $this->formatUser($user),
        ]);
    }

    /**
     * Logout user (revoke token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->token()->revoke();

        return response()->json([
            'message' => 'خروج موفقیت‌آمیز بود',
        ]);
    }

    /**
     * Update user profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'fname' => 'sometimes|string|max:255',
            'lname' => 'sometimes|string|max:255',
            'mobile' => 'sometimes|string|max:20',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->username . ',username',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update($request->only(['fname', 'lname', 'mobile', 'email']));

        return response()->json([
            'message' => 'پروفایل با موفقیت به‌روزرسانی شد',
            'data' => $this->formatUser($user->fresh()),
        ]);
    }

    /**
     * Update user password.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'رمز عبور فعلی نادرست است',
            ], 403);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'message' => 'رمز عبور با موفقیت تغییر یافت',
        ]);
    }

    /**
     * Verify user password (for standby unlock).
     */
    public function verifyPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'رمز عبور نادرست است',
            ], 403);
        }

        return response()->json([
            'message' => 'رمز عبور صحیح است',
        ]);
    }

    /**
     * Send password reset link.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // We use the standard Laravel password broker
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'لینک بازنشانی رمز عبور به ایمیل شما ارسال شد',
            ]);
        }

        return response()->json([
            'message' => 'ارسال ایمیل با مشکل مواجه شد',
        ], 500);
    }

    /**
     * Reset password using token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|string|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'رمز عبور با موفقیت بازنشانی شد',
            ]);
        }

        return response()->json([
            'message' => 'توکن بازنشانی نامعتبر است',
        ], 400);
    }

    /**
     * Switch the user's active (primary) role.
     * The role must exist in the user's roles table.
     */
    public function switchRole(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'role' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $newRole = $request->input('role');

        // Verify the user has this role in their roles table
        $roles = $user->roles;
        if (!in_array($newRole, $roles)) {
            return response()->json([
                'message' => 'شما به این نقش دسترسی ندارید',
            ], 403);
        }

        // Update the primary role
        $user->role = $newRole;
        $user->save();

        return response()->json([
            'message' => 'نقش کاربری با موفقیت تغییر یافت',
            'data' => $this->formatUser($user->fresh()),
        ]);
    }

    /**
     * Update user theme preference.
     */
    public function updateTheme(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'theme' => 'required|string|in:light,dark',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update([
            'theme' => $request->input('theme'),
        ]);

        return response()->json([
            'message' => 'تم با موفقیت ذخیره شد',
            'data' => $this->formatUser($user->fresh()),
        ]);
    }

    /**
     * Format user data for API response.
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
            'has_student_profile' => $user->student()->exists(),
            'has_teacher_profile' => $user->teacher()->exists(),
            'has_phd_profile' => $user->phd()->exists(),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
