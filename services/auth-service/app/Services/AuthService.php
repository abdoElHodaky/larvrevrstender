<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AuthService
{
    private OtpService $otpService;
    
    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }
    
    /**
     * Register a new user with phone verification
     */
    public function register(array $userData): array
    {
        try {
            DB::beginTransaction();
            
            // Check if user already exists
            if (User::where('phone', $userData['phone'])->exists()) {
                throw new \Exception('Phone number already registered');
            }
            
            if (isset($userData['email']) && User::where('email', $userData['email'])->exists()) {
                throw new \Exception('Email already registered');
            }
            
            // Create user
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'] ?? null,
                'phone' => $userData['phone'],
                'password' => Hash::make($userData['password']),
                'type' => $userData['type'] ?? 'customer', // customer, merchant, admin
                'phone_verified_at' => null
            ]);
            
            // Send OTP for phone verification
            $otpResult = $this->otpService->sendOtp($userData['phone']);
            
            if (!$otpResult['success']) {
                throw new \Exception('Failed to send verification code');
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'user_id' => $user->id,
                'message' => 'Registration successful. OTP sent to your phone.',
                'requires_verification' => true,
                'expires_at' => $otpResult['expires_at']
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Registration failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify OTP and complete registration/login
     */
    public function verifyOtp(int $userId, string $otpCode): array
    {
        try {
            $user = User::find($userId);
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }
            
            // Verify OTP using OtpService
            if (!$this->otpService->verifyOtp($user->phone, $otpCode)) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired OTP'
                ];
            }
            
            DB::beginTransaction();
            
            // Update user verification status
            $user->update([
                'phone_verified_at' => now(),
                'email_verified_at' => $user->email ? now() : null
            ]);
            
            // Generate Sanctum token
            $token = $user->createToken('auth-token', ['*'], now()->addDays(30))->plainTextToken;
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Verification successful',
                'user' => $user->fresh(),
                'access_token' => $token,
                'token_type' => 'Bearer'
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('OTP verification failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Verification failed'
            ];
        }
    }
    
    /**
     * Login user with phone and password
     */
    public function login(string $phone, string $password): array
    {
        try {
            $user = User::where('phone', $phone)->first();
            
            if (!$user || !Hash::check($password, $user->password)) {
                return [
                    'success' => false,
                    'message' => 'Invalid credentials'
                ];
            }
            
            if (!$user->phone_verified_at) {
                // Resend OTP for unverified users
                $otpResult = $this->otpService->sendOtp($user->phone);
                
                return [
                    'success' => false,
                    'message' => 'Phone number not verified. OTP sent.',
                    'requires_verification' => true,
                    'user_id' => $user->id,
                    'expires_at' => $otpResult['expires_at'] ?? null
                ];
            }
            
            // Generate Sanctum token
            $token = $user->createToken('auth-token', ['*'], now()->addDays(30))->plainTextToken;
            
            // Update last login
            $user->update(['last_login_at' => now()]);
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => $user->fresh(),
                'access_token' => $token,
                'token_type' => 'Bearer'
            ];
            
        } catch (\Exception $e) {
            Log::error('Login failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Login failed'
            ];
        }
    }
    
    /**
     * Logout user (revoke current token)
     */
    public function logout(User $user): array
    {
        try {
            // Revoke current access token
            $user->currentAccessToken()->delete();
            
            return [
                'success' => true,
                'message' => 'Logged out successfully'
            ];
            
        } catch (\Exception $e) {
            Log::error('Logout failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Logout failed'
            ];
        }
    }
    
    /**
     * Revoke all tokens for user
     */
    public function logoutAll(User $user): array
    {
        try {
            // Revoke all tokens
            $user->tokens()->delete();
            
            return [
                'success' => true,
                'message' => 'Logged out from all devices successfully'
            ];
            
        } catch (\Exception $e) {
            Log::error('Logout all failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Logout failed'
            ];
        }
    }
    
    /**
     * Refresh token (create new token and revoke old one)
     */
    public function refreshToken(User $user): array
    {
        try {
            // Revoke current token
            $user->currentAccessToken()->delete();
            
            // Create new token
            $token = $user->createToken('auth-token', ['*'], now()->addDays(30))->plainTextToken;
            
            return [
                'success' => true,
                'access_token' => $token,
                'token_type' => 'Bearer'
            ];
            
        } catch (\Exception $e) {
            Log::error('Token refresh failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Token refresh failed'
            ];
        }
    }
}
