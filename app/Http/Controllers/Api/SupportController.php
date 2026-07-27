<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class SupportController extends Controller
{
    private const SUPPORT_USERNAME = 'support';

    /**
     * Impersonate a user — create a token for the target user.
     * Only the support user can use this endpoint.
     */
    public function impersonate(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        // Only support user can impersonate
        if ($currentUser->username !== self::SUPPORT_USERNAME) {
            return response()->json([
                'message' => 'فقط کاربر پشتیبان امکان ورود به حساب کاربران دیگر را دارد',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|exists:users,username',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Cannot impersonate yourself
        if ($request->username === self::SUPPORT_USERNAME) {
            return response()->json([
                'message' => 'امکان ورود به حساب خود وجود ندارد',
            ], 422);
        }

        $targetUser = User::find($request->username);

        // Revoke any previous impersonation tokens for this support user to keep clean
        \Illuminate\Support\Facades\DB::table('oauth_access_tokens')
            ->where('name', 'support-impersonation')
            ->where('revoked', false)
            ->update(['revoked' => true]);

        // Create a new token for the target user (named differently for tracking)
        $tokenResult = $targetUser->createToken('support-impersonation');
        $token = $tokenResult->accessToken;

        // Store device info
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
            'message' => 'ورود به حساب کاربر ' . $targetUser->getName() . ' انجام شد',
            'data' => [
                'user' => [
                    'username'   => $targetUser->username,
                    'fname'      => $targetUser->fname,
                    'lname'      => $targetUser->lname,
                    'full_name'  => $targetUser->getName(),
                    'email'      => $targetUser->email,
                    'mobile'     => $targetUser->mobile,
                    'role'       => $targetUser->role,
                    'roles'      => $targetUser->getRoleNames()->toArray(),
                    'permissions' => $targetUser->getAllPermissions()->pluck('name')->toArray(),
                ],
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * End impersonation — revoke the impersonation token and issue a new support token.
     * The frontend sends the support user's token to authenticate this request.
     */
    public function endImpersonation(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        // Only support user can end impersonation
        if ($currentUser->username !== self::SUPPORT_USERNAME) {
            return response()->json([
                'message' => 'فقط کاربر پشتیبان امکان بازگشت به حساب خود را دارد',
            ], 403);
        }

        // Revoke all support-impersonation tokens (they belong to target users, not the support user)
        // The frontend will switch from the impersonation token to this new token
        \Illuminate\Support\Facades\DB::table('oauth_access_tokens')
            ->where('name', 'support-impersonation')
            ->where('revoked', false)
            ->update(['revoked' => true]);

        // Revoke old support tokens to keep clean
        $currentUser->tokens()->where('name', 'portal-api')->delete();

        // Create a fresh token for the support user
        $tokenResult = $currentUser->createToken('portal-api');
        $token = $tokenResult->accessToken;

        // Store device info
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
            'message' => 'بازگشت به حساب پشتیبان انجام شد',
            'data' => [
                'user' => [
                    'username'   => $currentUser->username,
                    'fname'      => $currentUser->fname,
                    'lname'      => $currentUser->lname,
                    'full_name'  => $currentUser->getName(),
                    'email'      => $currentUser->email,
                    'mobile'     => $currentUser->mobile,
                    'role'       => $currentUser->role,
                    'roles'      => $currentUser->getRoleNames()->toArray(),
                    'permissions' => $currentUser->getAllPermissions()->pluck('name')->toArray(),
                ],
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }
}
