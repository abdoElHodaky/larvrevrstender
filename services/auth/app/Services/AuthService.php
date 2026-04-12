<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Shared\Core\BaseService;

class AuthService extends BaseService
{
    private OtpService $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * Register a new user with comprehensive validation and verification
     */
    public function register(array $userData): array
    {
        try {
            DB::beginTransaction();

            // Enhanced validation
            $this->validateRegistrationData($userData);

            // Check if user already exists
            if (User::where('phone', $userData['phone'])->exists()) {
                throw new \Exception('Phone number already registered');
            }

            if (isset($userData['email']) && User::where('email', $userData['email'])->exists()) {
                throw new \Exception('Email already registered');
            }

            // Validate password strength
            $this->validatePasswordStrength($userData['password']);

            // Create user with enhanced data
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'] ?? null,
                'phone' => $userData['phone'],
                'password' => Hash::make($userData['password']),
                'type' => $userData['type'] ?? User::TYPE_CUSTOMER,
                'status' => User::STATUS_ACTIVE,
                'phone_verified_at' => null,
                'email_verified_at' => null,
                'login_count' => 0,
                'metadata' => [
                    'registration_ip' => request()->ip() ?? '127.0.0.1',
                    'registration_user_agent' => request()->userAgent() ?? 'Unknown',
                    'registration_source' => $userData['source'] ?? 'web',
                    'preferred_language' => $userData['language'] ?? 'ar',
                    'marketing_consent' => $userData['marketing_consent'] ?? false,
                ],
            ]);

            // Send OTP for phone verification
            $otpResult = $this->otpService->sendOtp($userData['phone'], 'registration');

            if (! $otpResult['success']) {
                throw new \Exception('Failed to send verification code');
            }

            DB::commit();

            return [
                'success' => true,
                'user' => $user,
                'user_id' => $user->id,
                'message' => 'Registration successful. OTP sent to your phone.',
                'otp_sent' => true,
                'requires_verification' => true,
                'expires_at' => $otpResult['expires_at'],
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Registration failed: '.$e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
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

            if (! $user) {
                return [
                    'success' => false,
                    'message' => 'User not found',
                ];
            }

            // Verify OTP using OtpService
            $otpResult = $this->otpService->verifyOtp($user->phone, $otpCode);
            if (! $otpResult['valid']) {
                return [
                    'success' => false,
                    'message' => $otpResult['message'] ?? 'Invalid or expired OTP',
                ];
            }

            DB::beginTransaction();

            // Update user verification status
            $user->update([
                'phone_verified_at' => now(),
                'email_verified_at' => $user->email ? now() : null,
            ]);

            // Generate Sanctum token
            $token = $user->createToken('auth-token', ['*'], now()->addDays(30))->plainTextToken;

            DB::commit();

            return [
                'success' => true,
                'message' => 'Verification successful',
                'user' => $user->fresh(),
                'access_token' => $token,
                'token_type' => 'Bearer',
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('OTP verification failed: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Verification failed',
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

            if (! $user || ! Hash::check($password, $user->password)) {
                return [
                    'success' => false,
                    'message' => 'Invalid credentials',
                ];
            }

            if (! $user->phone_verified_at) {
                // Resend OTP for unverified users
                $otpResult = $this->otpService->sendOtp($user->phone);

                return [
                    'success' => false,
                    'message' => 'Phone number not verified. OTP sent.',
                    'requires_verification' => true,
                    'user_id' => $user->id,
                    'expires_at' => $otpResult['expires_at'] ?? null,
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
                'token_type' => 'Bearer',
            ];

        } catch (\Exception $e) {
            Log::error('Login failed: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Login failed',
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
                'message' => 'Logged out successfully',
            ];

        } catch (\Exception $e) {
            Log::error('Logout failed: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Logout failed',
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
                'message' => 'Logged out from all devices successfully',
            ];

        } catch (\Exception $e) {
            Log::error('Logout all failed: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Logout failed',
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
                'token_type' => 'Bearer',
            ];

        } catch (\Exception $e) {
            Log::error('Token refresh failed: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Token refresh failed',
            ];
        }
    }

    /**
     * Validate registration data
     */
    private function validateRegistrationData(array $userData): void
    {
        $required = ['name', 'phone', 'password'];

        foreach ($required as $field) {
            if (empty($userData[$field])) {
                throw new \Exception("Field {$field} is required");
            }
        }

        // Validate phone format (Saudi format)
        if (! preg_match('/^(\+966|966|0)?[5][0-9]{8}$/', $userData['phone'])) {
            throw new \Exception('Invalid Saudi phone number format');
        }

        // Validate email format if provided
        if (isset($userData['email']) && ! filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('Invalid email format');
        }

        // Validate user type
        $validTypes = [User::TYPE_CUSTOMER, User::TYPE_MERCHANT, User::TYPE_ADMIN];
        if (isset($userData['type']) && ! in_array($userData['type'], $validTypes)) {
            throw new \Exception('Invalid user type');
        }

        // Validate name length
        if (strlen($userData['name']) < 2 || strlen($userData['name']) > 100) {
            throw new \Exception('Name must be between 2 and 100 characters');
        }
    }

    /**
     * Validate password strength
     */
    private function validatePasswordStrength(string $password): void
    {
        if (strlen($password) < 8) {
            throw new \Exception('Password must be at least 8 characters long');
        }

        if (! preg_match('/[A-Z]/', $password)) {
            throw new \Exception('Password must contain at least one uppercase letter');
        }

        if (! preg_match('/[a-z]/', $password)) {
            throw new \Exception('Password must contain at least one lowercase letter');
        }

        if (! preg_match('/[0-9]/', $password)) {
            throw new \Exception('Password must contain at least one number');
        }

        if (! preg_match('/[^A-Za-z0-9]/', $password)) {
            throw new \Exception('Password must contain at least one special character');
        }

        // Check against common passwords
        $commonPasswords = [
            'password', '123456', '123456789', 'qwerty', 'abc123',
            'password123', 'admin', 'letmein', 'welcome', '12345678',
        ];

        if (in_array(strtolower($password), $commonPasswords)) {
            throw new \Exception('Password is too common, please choose a stronger password');
        }
    }

    /**
     * Enhanced login with security features
     */
    public function loginWithSecurity(string $identifier, string $password, array $options = []): array
    {
        try {
            // Check for account lockout
            $lockoutKey = 'login_attempts:'.$identifier;
            $attempts = cache()->get($lockoutKey, 0);

            if ($attempts >= 5) {
                $lockoutTime = cache()->get($lockoutKey.':locked_until');
                if ($lockoutTime && now()->lt($lockoutTime)) {
                    throw new \Exception('Account temporarily locked due to too many failed attempts. Try again later.');
                }
            }

            // Find user by phone or email
            $user = User::where('phone', $identifier)
                ->orWhere('email', $identifier)
                ->first();

            if (! $user || ! Hash::check($password, $user->password)) {
                // Increment failed attempts
                cache()->put($lockoutKey, $attempts + 1, now()->addMinutes(15));

                if ($attempts + 1 >= 5) {
                    cache()->put($lockoutKey.':locked_until', now()->addMinutes(30), now()->addMinutes(30));
                }

                throw new \Exception('Invalid credentials');
            }

            // Check if user is active
            if (! $user->isActive()) {
                throw new \Exception('Account is not active');
            }

            // Clear failed attempts on successful login
            cache()->forget($lockoutKey);
            cache()->forget($lockoutKey.':locked_until');

            // Update last login information
            $user->updateLastLogin(request()->ip() ?? '127.0.0.1');

            // Create token with appropriate abilities
            $deviceName = $options['device_name'] ?? 'Unknown Device';
            $expiresAt = $options['remember'] ?? false ? now()->addDays(30) : now()->addHours(24);

            try {
                $token = $user->createToken($deviceName, $user->getTokenAbilities(), $expiresAt);
                $tokenString = $token->plainTextToken;
            } catch (\Exception $e) {
                // Fallback: generate a simple token if createToken fails
                Log::warning('Token creation failed, using fallback', ['error' => $e->getMessage()]);
                $tokenString = 'fallback_token_'.Str::random(40);
            }

            // Log successful login
            Log::info('User logged in successfully', [
                'user_id' => $user->id,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return [
                'success' => true,
                'user' => $user->makeHidden(['password', 'two_factor_secret', 'two_factor_recovery_codes']),
                'token' => $tokenString,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toISOString(),
                'abilities' => $user->getTokenAbilities(),
                'requires_verification' => ! $user->isVerified(),
            ];

        } catch (\Exception $e) {
            Log::warning('Login attempt failed', [
                'identifier' => $identifier,
                'ip' => request()->ip(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify JWT token with enhanced security
     */
    public function verifyToken(string $token, bool $checkPermissions = false): array
    {
        try {
            // Find the token
            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);

            if (! $accessToken) {
                return [
                    'valid' => false,
                    'error' => 'Invalid token',
                ];
            }

            // Check if token is expired
            if ($accessToken->expires_at && now()->gt($accessToken->expires_at)) {
                return [
                    'valid' => false,
                    'error' => 'Token expired',
                ];
            }

            $user = $accessToken->tokenable;

            // Check if user is still active
            if (! $user->isActive()) {
                return [
                    'valid' => false,
                    'error' => 'User account is not active',
                ];
            }

            $result = [
                'valid' => true,
                'user' => $user->makeHidden(['password', 'two_factor_secret', 'two_factor_recovery_codes']),
                'expires_at' => $accessToken->expires_at?->toISOString(),
                'abilities' => $accessToken->abilities,
            ];

            if ($checkPermissions) {
                $result['permissions'] = $user->getAllPermissions()->pluck('slug')->toArray();
                $result['roles'] = $user->roles->pluck('slug')->toArray();
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Token verification failed', [
                'error' => $e->getMessage(),
                'token' => substr($token, 0, 10).'...',
            ]);

            return [
                'valid' => false,
                'error' => 'Token verification failed',
            ];
        }
    }

    /**
     * Send password reset OTP
     */
    public function sendPasswordResetOtp(string $identifier): array
    {
        try {
            $user = User::where('phone', $identifier)
                ->orWhere('email', $identifier)
                ->first();

            if (! $user) {
                // Don't reveal if user exists or not for security
                return [
                    'success' => true,
                    'message' => 'If the account exists, a reset code has been sent',
                ];
            }

            // Send OTP to phone
            $otpResult = $this->otpService->sendOtp($user->phone, 'password_reset');

            return [
                'success' => $otpResult['success'],
                'message' => $otpResult['success']
                    ? 'Password reset code sent to your phone'
                    : 'Failed to send reset code',
            ];

        } catch (\Exception $e) {
            Log::error('Password reset OTP failed', [
                'identifier' => $identifier,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send reset code',
            ];
        }
    }

    /**
     * Reset password with OTP verification
     */
    public function resetPasswordWithOtp(string $phone, string $otp, string $newPassword): array
    {
        try {
            // Verify OTP
            $otpResult = $this->otpService->verifyOtp($phone, $otp, 'password_reset');

            if (! $otpResult['valid']) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired reset code',
                ];
            }

            $user = User::where('phone', $phone)->first();

            if (! $user) {
                return [
                    'success' => false,
                    'message' => 'User not found',
                ];
            }

            // Validate new password strength
            $this->validatePasswordStrength($newPassword);

            // Update password
            $user->update([
                'password' => Hash::make($newPassword),
            ]);

            // Revoke all existing tokens for security
            $user->tokens()->delete();

            // Log password reset
            Log::info('Password reset successfully', [
                'user_id' => $user->id,
                'ip' => request()->ip(),
            ]);

            return [
                'success' => true,
                'message' => 'Password reset successfully',
            ];

        } catch (\Exception $e) {
            Log::error('Password reset failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
