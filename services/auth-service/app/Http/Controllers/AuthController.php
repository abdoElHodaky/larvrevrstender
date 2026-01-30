<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|saudi_phone|unique:users',
            'password' => 'required|string|min:8|strong_password|confirmed',
            'type' => 'required|in:customer,merchant',
        ]);

        if ($validator->fails()) {
            return response()->error('Validation failed', $validator->errors(), 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'type' => $request->type,
            'status' => User::STATUS_ACTIVE,
        ]);

        // Create Sanctum token with user-specific abilities
        $token = $user->createToken('auth-token', $user->getTokenAbilities());

        return response()->success([
            'user' => $user,
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
        ], 'User registered successfully', 201);
    }

    /**
     * Login user
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->error('Validation failed', $validator->errors(), 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->error('Invalid credentials', null, 401);
        }

        if (!$user->isActive()) {
            return response()->error('Account is not active', null, 403);
        }

        // Update login tracking
        $user->updateLastLogin($request->ip());

        // Create Sanctum token with user-specific abilities
        $token = $user->createToken('auth-token', $user->getTokenAbilities());

        return response()->success([
            'user' => $user,
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
        ], 'Login successful');
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
            'phone' => 'sometimes|string|saudi_phone|unique:users,phone,' . $user->id,
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

        if (!$token) {
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
     * Logout user (revoke current token)
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->success(null, 'Logged out successfully');
    }

    /**
     * Get all users (Admin only)
     */
    public function getUsers(Request $request): JsonResponse
    {
        // Check authorization using gate
        if (!Gate::allows('manage-users')) {
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
        if (!Gate::allows('admin-access')) {
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
        if (!Gate::allows('admin-access')) {
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
        if (!Gate::allows('manage-2fa', $user)) {
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
        if (!Gate::allows('manage-2fa', $user)) {
            return response()->error('Unauthorized', null, 403);
        }

        // Implementation for 2FA disable would go here
        // This is a placeholder

        return response()->success(null, 'Two-factor authentication disabled');
    }
}

