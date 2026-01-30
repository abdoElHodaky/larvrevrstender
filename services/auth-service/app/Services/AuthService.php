<?php

namespace App\Services;

use App\Models\User;
use App\Models\Otp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;

class AuthService
{
    private SmsService $smsService;
    
    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
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
            
            // Create user
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'] ?? null,
                'phone' => $userData['phone'],
                'password' => Hash::make($userData['password']),
                'type' => $userData['type'], // customer, merchant, admin
                'verified' => false
            ]);
            
            // Generate and send OTP
            $otpCode = $this->generateOtp($user->id, 'phone_verification');
            $this->smsService->sendOtp($user->phone, $otpCode);
            
            DB::commit();
            
            return [
                'success' => true,
                'user_id' => $user->id,
                'message' => 'Registration successful. OTP sent to your phone.',
                'requires_verification' => true
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
     * Verify OTP and complete registration
     */
    public function verifyOtp(int $userId, string $otpCode, string $type = 'phone_verification'): array
    {
        try {
            $otp = Otp::where('user_id', $userId)
                ->where('code', $otpCode)
                ->where('type', $type)
                ->where('expires_at', '>', now())
                ->where('used', false)
                ->first();
                
            if (!$otp) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired OTP'
                ];
            }
            
            DB::beginTransaction();
            
            // Mark OTP as used
            $otp->update(['used' => true]);
            
            // Update user verification status
            $user = User::find($userId);
            if ($type === 'phone_verification') {
                $user->update([
                    'verified' => true,
                    'phone_verified_at' => now()
                ]);
            }
            
            // Generate JWT token
            $token = JWTAuth::fromUser($user);
            $refreshToken = $this->generateRefreshToken($user);
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Verification successful',
                'user' => $user->load('profile'),
                'access_token' => $token,
                'refresh_token' => $refreshToken,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60
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
            
            if (!$user->verified) {
                // Resend OTP for unverified users
                $otpCode = $this->generateOtp($user->id, 'phone_verification');
                $this->smsService->sendOtp($user->phone, $otpCode);
                
                return [
                    'success' => false,
                    'message' => 'Phone number not verified. OTP sent.',
                    'requires_verification' => true,
                    'user_id' => $user->id
                ];
            }
            
            // Generate tokens
            $token = JWTAuth::fromUser($user);
            $refreshToken = $this->generateRefreshToken($user);
            
            // Update last login
            $user->update(['last_login_at' => now()]);
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => $user->load('profile'),
                'access_token' => $token,
                'refresh_token' => $refreshToken,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60
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
     * Generate OTP code
     */
    private function generateOtp(int $userId, string $type): string
    {
        // Invalidate existing OTPs
        Otp::where('user_id', $userId)
            ->where('type', $type)
            ->update(['used' => true]);
            
        // Generate new OTP
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        Otp::create([
            'user_id' => $userId,
            'code' => $code,
            'type' => $type,
            'expires_at' => now()->addMinutes(10) // 10 minutes expiry
        ]);
        
        return $code;
    }
    
    /**
     * Generate refresh token
     */
    private function generateRefreshToken(User $user): string
    {
        return base64_encode(json_encode([
            'user_id' => $user->id,
            'expires_at' => now()->addDays(30)->timestamp,
            'token' => bin2hex(random_bytes(32))
        ]));
    }
}

