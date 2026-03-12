<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuthService;
use App\Services\SocialAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    private AuthService $authService;

    private SocialAuthService $socialAuthService;

    public function __construct(AuthService $authService, SocialAuthService $socialAuthService)
    {
        $this->authService = $authService;
        $this->socialAuthService = $socialAuthService;
    }

    /**
     * Register a new user
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users',
            'phone' => 'required|string|saudi_phone|unique:users',
            'password' => 'required|string|min:8|strong_password|confirmed',
            'type' => 'nullable|in:customer,merchant',
        ]);

        if ($validator->fails()) {
            return response()->error('Validation failed', $validator->errors(), 422);
        }

        $result = $this->authService->register($request->all());

        if (! $result['success']) {
            return response()->error($result['message'], null, 400);
        }

        return response()->success([
            'user_id' => $result['user_id'],
            'requires_verification' => $result['requires_verification'],
            'expires_at' => $result['expires_at'] ?? null,
        ], $result['message'], 201);
    }

    /**
     * Login user with phone and password
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|saudi_phone',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->error('Validation failed', $validator->errors(), 422);
        }

        $result = $this->authService->login($request->phone, $request->password);

        if (! $result['success']) {
            $statusCode = isset($result['requires_verification']) ? 202 : 401;

            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'requires_verification' => $result['requires_verification'] ?? false,
                'user_id' => $result['user_id'] ?? null,
                'expires_at' => $result['expires_at'] ?? null,
            ], $statusCode);
        }

        return response()->success([
            'user' => $result['user'],
            'access_token' => $result['access_token'],
            'token_type' => $result['token_type'],
        ], $result['message']);
    }

    /**
     * Verify OTP for registration or login
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->error('Validation failed', $validator->errors(), 422);
        }

        $result = $this->authService->verifyOtp($request->user_id, $request->otp);

        if (! $result['success']) {
            return response()->error($result['message'], null, 400);
        }

        return response()->success([
            'user' => $result['user'],
            'access_token' => $result['access_token'],
            'token_type' => $result['token_type'],
        ], $result['message']);
    }

    /**
     * Logout user (revoke current token)
     */
    public function logout(Request $request): JsonResponse
    {
        $result = $this->authService->logout($request->user());

        if (! $result['success']) {
            return response()->error($result['message'], null, 500);
        }

        return response()->success(null, $result['message']);
    }

    /**
     * Logout from all devices (revoke all tokens)
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $result = $this->authService->logoutAll($request->user());

        if (! $result['success']) {
            return response()->error($result['message'], null, 500);
        }

        return response()->success(null, $result['message']);
    }

    /**
     * Refresh access token
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $result = $this->authService->refreshToken($request->user());

        if (! $result['success']) {
            return response()->error($result['message'], null, 500);
        }

        return response()->success([
            'access_token' => $result['access_token'],
            'token_type' => $result['token_type'],
        ], 'Token refreshed successfully');
    }

    /**
     * Get authenticated user profile
     */
    public function me(Request $request): JsonResponse
    {
        return response()->success($request->user(), 'User profile retrieved');
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check authorization using policy
        $this->authorize('update', $user);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|saudi_phone|unique:users,phone,'.$user->id,
        ]);

        if ($validator->fails()) {
            return response()->error('Validation failed', $validator->errors(), 422);
        }

        $user->update($request->only(['name', 'phone']));

        return response()->success($user, 'Profile updated successfully');
    }

    /**
     * Delete user account
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check authorization using policy
        $this->authorize('delete', $user);

        // Revoke all tokens
        $user->tokens()->delete();

        // Soft delete the user
        $user->delete();

        return response()->success(null, 'Account deleted successfully');
    }

    /**
     * Get user sessions
     */
    public function getSessions(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check authorization using policy
        $this->authorize('manageSessions', $user);

        $sessions = $user->tokens()->get();

        return response()->success($sessions, 'Sessions retrieved');
    }

    /**
     * Revoke specific session
     */
    public function revokeSession(Request $request, $sessionId): JsonResponse
    {
        $user = $request->user();

        // Check authorization using policy
        $this->authorize('revokeSessions', $user);

        $token = $user->tokens()->where('id', $sessionId)->first();

        if (! $token) {
            return response()->error('Session not found', null, 404);
        }

        $token->delete();

        return response()->success(null, 'Session revoked successfully');
    }

    /**
     * Revoke all sessions
     */
    public function revokeAllSessions(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check authorization using policy
        $this->authorize('revokeSessions', $user);

        $user->tokens()->delete();

        return response()->success(null, 'All sessions revoked successfully');
    }

    /**
     * Get all users (Admin only)
     */
    public function getUsers(Request $request): JsonResponse
    {
        // Check authorization using gate
        if (! Gate::allows('manage-users')) {
            return response()->error('Unauthorized', null, 403);
        }

        $users = User::with(['customerProfile', 'merchantProfile'])
            ->paginate($request->get('per_page', 15));

        return response()->success($users, 'Users retrieved');
    }

    /**
     * Get specific user (Admin only)
     */
    public function getUser(Request $request, $userId): JsonResponse
    {
        $targetUser = User::findOrFail($userId);

        // Check authorization using policy
        $this->authorize('view', $targetUser);

        return response()->success($targetUser, 'User retrieved');
    }

    /**
     * Update user status (Admin only)
     */
    public function updateUserStatus(Request $request, $userId): JsonResponse
    {
        $targetUser = User::findOrFail($userId);

        // Check authorization using policy
        $this->authorize('changeStatus', $targetUser);

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,inactive,suspended,banned',
        ]);

        if ($validator->fails()) {
            return response()->error('Validation failed', $validator->errors(), 422);
        }

        $targetUser->update(['status' => $request->status]);

        return response()->success($targetUser, 'User status updated');
    }

    /**
     * Delete user (Admin only)
     */
    public function deleteUser(Request $request, $userId): JsonResponse
    {
        $targetUser = User::findOrFail($userId);

        // Check authorization using policy
        $this->authorize('delete', $targetUser);

        // Revoke all user tokens
        $targetUser->tokens()->delete();

        // Soft delete the user
        $targetUser->delete();

        return response()->success(null, 'User deleted successfully');
    }

    /**
     * Get all sessions (Admin only)
     */
    public function getAllSessions(Request $request): JsonResponse
    {
        // Check authorization using gate
        if (! Gate::allows('admin-access')) {
            return response()->error('Unauthorized', null, 403);
        }

        // This would typically get sessions from a sessions table
        // For now, we'll return active tokens
        $sessions = \Laravel\Sanctum\PersonalAccessToken::with('tokenable')
            ->paginate($request->get('per_page', 15));

        return response()->success($sessions, 'All sessions retrieved');
    }

    /**
     * Revoke user session (Admin only)
     */
    public function revokeUserSession(Request $request, $sessionId): JsonResponse
    {
        // Check authorization using gate
        if (! Gate::allows('admin-access')) {
            return response()->error('Unauthorized', null, 403);
        }

        $token = \Laravel\Sanctum\PersonalAccessToken::findOrFail($sessionId);
        $token->delete();

        return response()->success(null, 'User session revoked successfully');
    }

    /**
     * Enable two-factor authentication
     */
    public function enableTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check authorization using gate
        if (! Gate::allows('manage-2fa', $user)) {
            return response()->error('Unauthorized', null, 403);
        }

        // Implementation for 2FA setup would go here
        // This is a placeholder

        return response()->success(null, 'Two-factor authentication enabled');
    }

    /**
     * Disable two-factor authentication
     */
    public function disableTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check authorization using gate
        if (! Gate::allows('manage-2fa', $user)) {
            return response()->error('Unauthorized', null, 403);
        }

        // Implementation for 2FA disable would go here
        // This is a placeholder

        return response()->success(null, 'Two-factor authentication disabled');
    }

    /**
     * Create a new session for inter-service communication
     */
    public function createSession(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->error('Validation failed', $validator->errors(), 422);
        }

        try {
            $result = $this->authService->createSession($request->user_id, $request->metadata ?? []);

            if (!$result['success']) {
                return response()->error($result['message'], null, 400);
            }

            return response()->success([
                'session_id' => $result['session_id'],
                'session_token' => $result['session_token'],
                'expires_at' => $result['expires_at'],
            ], 'Session created successfully');

        } catch (\Exception $e) {
            return response()->error('Failed to create session', null, 500);
        }
    }

    /**
     * Invalidate a session for inter-service communication
     */
    public function invalidateSession(Request $request, string $sessionId): JsonResponse
    {
        try {
            $result = $this->authService->invalidateSession($sessionId);

            if (!$result['success']) {
                return response()->error($result['message'], null, 404);
            }

            return response()->success(null, 'Session invalidated successfully');

        } catch (\Exception $e) {
            return response()->error('Failed to invalidate session', null, 500);
        }
    }

    /**
     * Redirect to social provider
     */
    public function socialRedirect(string $provider): JsonResponse
    {
        if (! in_array($provider, $this->socialAuthService->getSupportedProviders())) {
            return response()->error('Unsupported provider', null, 400);
        }

        try {
            $redirectUrl = $this->socialAuthService->getRedirectUrl($provider);

            return response()->success([
                'redirect_url' => $redirectUrl,
            ], 'Redirect URL generated');

        } catch (\Exception $e) {
            return response()->error('Failed to generate redirect URL', null, 500);
        }
    }

    /**
     * Handle social provider callback
     */
    public function socialCallback(string $provider): JsonResponse
    {
        if (! in_array($provider, $this->socialAuthService->getSupportedProviders())) {
            return response()->error('Unsupported provider', null, 400);
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
            $result = $this->socialAuthService->handleSocialAuth($provider, $socialUser);

            if (! $result['success']) {
                return response()->error($result['message'], null, 400);
            }

            return response()->success([
                'user' => $result['user'],
                'access_token' => $result['token'],
                'token_type' => $result['token_type'],
            ], 'Social authentication successful');

        } catch (\Exception $e) {
            return response()->error('Social authentication failed', null, 400);
        }
    }

    /**
     * Validate a token for inter-service communication
     */
    public function validateToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->error('Validation failed', $validator->errors(), 422);
        }

        try {
            $result = $this->authService->validateToken($request->token);

            return response()->success($result, 'Token validated');

        } catch (\Exception $e) {
            return response()->error('Token validation failed', null, 401);
        }
    }

    /**
     * Get user permissions for inter-service communication
     */
    public function getUserPermissions(Request $request, int $userId): JsonResponse
    {
        try {
            $permissions = $this->authService->getUserPermissions($userId);

            return response()->success([
                'user_id' => $userId,
                'permissions' => $permissions,
            ], 'Permissions retrieved');

        } catch (\Exception $e) {
            return response()->error('Failed to retrieve permissions', null, 500);
        }
    }

    /**
     * Check if user has specific permission for inter-service communication
     */
    public function checkPermission(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'permission' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->error('Validation failed', $validator->errors(), 422);
        }

        try {
            $hasPermission = $this->authService->hasPermission($request->user_id, $request->permission);

            return response()->success([
                'user_id' => $request->user_id,
                'permission' => $request->permission,
                'has_permission' => $hasPermission,
            ], 'Permission checked');

        } catch (\Exception $e) {
            return response()->error('Permission check failed', null, 500);
        }
    }

    /**
     * Log user activity for inter-service communication
     */
    public function logActivity(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'action' => 'required|string',
            'metadata' => 'nullable|array',
            'timestamp' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->error('Validation failed', $validator->errors(), 422);
        }

        try {
            $this->authService->logActivity(
                $request->user_id,
                $request->action,
                $request->metadata ?? [],
                $request->timestamp
            );

            return response()->success(null, 'Activity logged');

        } catch (\Exception $e) {
            return response()->error('Activity logging failed', null, 500);
        }
    }

    /**
     * Get user roles for inter-service communication
     */
    public function getUserRoles(Request $request, int $userId): JsonResponse
    {
        try {
            $roles = $this->authService->getUserRoles($userId);

            return response()->success([
                'user_id' => $userId,
                'roles' => $roles,
            ], 'Roles retrieved');

        } catch (\Exception $e) {
            return response()->error('Failed to retrieve roles', null, 500);
        }
    }

    /**
     * Check if user has specific role for inter-service communication
     */
    public function checkRole(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'role' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->error('Validation failed', $validator->errors(), 422);
        }

        try {
            $hasRole = $this->authService->hasRole($request->user_id, $request->role);

            return response()->success([
                'user_id' => $request->user_id,
                'role' => $request->role,
                'has_role' => $hasRole,
            ], 'Role checked');

        } catch (\Exception $e) {
            return response()->error('Role check failed', null, 500);
        }
    }

    /**
     * Get user tokens
     */
    public function getTokens(Request $request): JsonResponse
    {
        $user = $request->user();
        $tokens = $user->tokens()->get();

        return response()->success($tokens, 'Tokens retrieved');
    }

    /**
     * Revoke specific token
     */
    public function revokeToken(Request $request, string $tokenId): JsonResponse
    {
        $user = $request->user();
        $token = $user->tokens()->where('id', $tokenId)->first();

        if (!$token) {
            return response()->error('Token not found', null, 404);
        }

        $token->delete();

        return response()->success(null, 'Token revoked successfully');
    }

    /**
     * Revoke all tokens
     */
    public function revokeAllTokens(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->delete();

        return response()->success(null, 'All tokens revoked successfully');
    }
}
