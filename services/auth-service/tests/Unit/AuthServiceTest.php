<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\AuthService;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Mockery;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $authService;
    private OtpService $otpService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock OtpService
        $this->otpService = Mockery::mock(OtpService::class);
        $this->authService = new AuthService($this->otpService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_register_a_new_user_successfully()
    {
        // Mock OTP service
        $this->otpService->shouldReceive('sendOtp')
            ->once()
            ->with('+966501234567', 'registration')
            ->andReturn([
                'success' => true,
                'message' => 'OTP sent successfully',
            ]);

        $userData = [
            'name' => 'Ahmed Al-Saudi',
            'phone' => '+966501234567',
            'email' => 'ahmed@example.com',
            'password' => 'SecurePass123!',
            'type' => 'customer',
            'source' => 'web',
            'language' => 'ar',
            'marketing_consent' => true,
        ];

        $result = $this->authService->register($userData);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('otp_sent', $result);
        
        // Verify user was created in database
        $this->assertDatabaseHas('users', [
            'name' => 'Ahmed Al-Saudi',
            'phone' => '+966501234567',
            'email' => 'ahmed@example.com',
            'type' => User::TYPE_CUSTOMER,
            'status' => User::STATUS_ACTIVE,
        ]);

        // Verify password is hashed
        $user = User::where('phone', '+966501234567')->first();
        $this->assertTrue(Hash::check('SecurePass123!', $user->password));
        
        // Verify metadata
        $this->assertArrayHasKey('registration_source', $user->metadata);
        $this->assertEquals('web', $user->metadata['registration_source']);
        $this->assertEquals('ar', $user->metadata['preferred_language']);
        $this->assertTrue($user->metadata['marketing_consent']);
    }

    /** @test */
    public function it_validates_registration_data()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Field name is required');

        $userData = [
            'phone' => '+966501234567',
            'password' => 'SecurePass123!',
        ];

        $this->authService->register($userData);
    }

    /** @test */
    public function it_validates_saudi_phone_number_format()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid Saudi phone number format');

        $userData = [
            'name' => 'Ahmed Al-Saudi',
            'phone' => '+1234567890', // Invalid Saudi format
            'password' => 'SecurePass123!',
        ];

        $this->authService->register($userData);
    }

    /** @test */
    public function it_validates_password_strength()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Password must contain at least one uppercase letter');

        $userData = [
            'name' => 'Ahmed Al-Saudi',
            'phone' => '+966501234567',
            'password' => 'weakpassword', // No uppercase
        ];

        $this->authService->register($userData);
    }

    /** @test */
    public function it_rejects_common_passwords()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Password is too common');

        $userData = [
            'name' => 'Ahmed Al-Saudi',
            'phone' => '+966501234567',
            'password' => 'Password123', // Common password pattern
        ];

        $this->authService->register($userData);
    }

    /** @test */
    public function it_prevents_duplicate_phone_registration()
    {
        // Create existing user
        User::factory()->create([
            'phone' => '+966501234567',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Phone number already registered');

        $userData = [
            'name' => 'Ahmed Al-Saudi',
            'phone' => '+966501234567',
            'password' => 'SecurePass123!',
        ];

        $this->authService->register($userData);
    }

    /** @test */
    public function it_can_login_with_security_features()
    {
        // Create test user
        $user = User::factory()->create([
            'phone' => '+966501234567',
            'email' => 'ahmed@example.com',
            'password' => Hash::make('SecurePass123!'),
            'status' => User::STATUS_ACTIVE,
        ]);

        $result = $this->authService->loginWithSecurity(
            '+966501234567',
            'SecurePass123!',
            ['device_name' => 'Test Device']
        );

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertArrayHasKey('abilities', $result);
        
        // Verify last login was updated
        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertEquals(1, $user->login_count);
    }

    /** @test */
    public function it_implements_account_lockout_after_failed_attempts()
    {
        // Create test user
        User::factory()->create([
            'phone' => '+966501234567',
            'password' => Hash::make('SecurePass123!'),
            'status' => User::STATUS_ACTIVE,
        ]);

        // Simulate 5 failed login attempts
        for ($i = 0; $i < 5; $i++) {
            $result = $this->authService->loginWithSecurity(
                '+966501234567',
                'WrongPassword',
                ['device_name' => 'Test Device']
            );
            $this->assertFalse($result['success']);
        }

        // 6th attempt should be locked out
        $result = $this->authService->loginWithSecurity(
            '+966501234567',
            'WrongPassword',
            ['device_name' => 'Test Device']
        );

        $this->assertFalse($result['success']);
        $this->assertStringContains('temporarily locked', $result['message']);
    }

    /** @test */
    public function it_rejects_login_for_inactive_users()
    {
        // Create inactive user
        User::factory()->create([
            'phone' => '+966501234567',
            'password' => Hash::make('SecurePass123!'),
            'status' => User::STATUS_INACTIVE,
        ]);

        $result = $this->authService->loginWithSecurity(
            '+966501234567',
            'SecurePass123!',
            ['device_name' => 'Test Device']
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('Account is not active', $result['message']);
    }

    /** @test */
    public function it_can_verify_valid_tokens()
    {
        // Create test user with token
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
        ]);

        $token = $user->createToken('Test Token', ['*'], now()->addHours(1));

        $result = $this->authService->verifyToken($token->plainTextToken);

        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertArrayHasKey('abilities', $result);
    }

    /** @test */
    public function it_rejects_expired_tokens()
    {
        // Create test user with expired token
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
        ]);

        $token = $user->createToken('Test Token', ['*'], now()->subHours(1)); // Expired

        $result = $this->authService->verifyToken($token->plainTextToken);

        $this->assertFalse($result['valid']);
        $this->assertEquals('Token expired', $result['error']);
    }

    /** @test */
    public function it_can_send_password_reset_otp()
    {
        // Create test user
        $user = User::factory()->create([
            'phone' => '+966501234567',
        ]);

        // Mock OTP service
        $this->otpService->shouldReceive('sendOtp')
            ->once()
            ->with('+966501234567', 'password_reset')
            ->andReturn([
                'success' => true,
                'message' => 'OTP sent successfully',
            ]);

        $result = $this->authService->sendPasswordResetOtp('+966501234567');

        $this->assertTrue($result['success']);
        $this->assertStringContains('reset code sent', $result['message']);
    }

    /** @test */
    public function it_does_not_reveal_user_existence_in_password_reset()
    {
        // Mock OTP service (should not be called for non-existent user)
        $this->otpService->shouldNotReceive('sendOtp');

        $result = $this->authService->sendPasswordResetOtp('+966999999999');

        // Should return success even for non-existent user
        $this->assertTrue($result['success']);
        $this->assertStringContains('If the account exists', $result['message']);
    }

    /** @test */
    public function it_can_reset_password_with_valid_otp()
    {
        // Create test user
        $user = User::factory()->create([
            'phone' => '+966501234567',
            'password' => Hash::make('OldPassword123!'),
        ]);

        // Mock OTP verification
        $this->otpService->shouldReceive('verifyOtp')
            ->once()
            ->with('+966501234567', '123456', 'password_reset')
            ->andReturn([
                'valid' => true,
                'message' => 'OTP verified successfully',
            ]);

        $result = $this->authService->resetPasswordWithOtp(
            '+966501234567',
            '123456',
            'NewSecurePass123!'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('Password reset successfully', $result['message']);

        // Verify password was changed
        $user->refresh();
        $this->assertTrue(Hash::check('NewSecurePass123!', $user->password));
        $this->assertFalse(Hash::check('OldPassword123!', $user->password));

        // Verify all tokens were revoked
        $this->assertEquals(0, $user->tokens()->count());
    }

    /** @test */
    public function it_validates_new_password_strength_during_reset()
    {
        // Create test user
        User::factory()->create([
            'phone' => '+966501234567',
        ]);

        // Mock OTP verification
        $this->otpService->shouldReceive('verifyOtp')
            ->once()
            ->with('+966501234567', '123456', 'password_reset')
            ->andReturn([
                'valid' => true,
                'message' => 'OTP verified successfully',
            ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Password must contain at least one uppercase letter');

        $this->authService->resetPasswordWithOtp(
            '+966501234567',
            '123456',
            'weakpassword' // Weak password
        );
    }

    /** @test */
    public function it_handles_invalid_otp_during_password_reset()
    {
        // Create test user
        User::factory()->create([
            'phone' => '+966501234567',
        ]);

        // Mock OTP verification failure
        $this->otpService->shouldReceive('verifyOtp')
            ->once()
            ->with('+966501234567', '123456', 'password_reset')
            ->andReturn([
                'valid' => false,
                'message' => 'Invalid OTP code',
            ]);

        $result = $this->authService->resetPasswordWithOtp(
            '+966501234567',
            '123456',
            'NewSecurePass123!'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid or expired reset code', $result['message']);
    }

    /** @test */
    public function it_supports_login_with_both_phone_and_email()
    {
        // Create test user
        $user = User::factory()->create([
            'phone' => '+966501234567',
            'email' => 'ahmed@example.com',
            'password' => Hash::make('SecurePass123!'),
            'status' => User::STATUS_ACTIVE,
        ]);

        // Test login with phone
        $result1 = $this->authService->loginWithSecurity(
            '+966501234567',
            'SecurePass123!'
        );
        $this->assertTrue($result1['success']);

        // Test login with email
        $result2 = $this->authService->loginWithSecurity(
            'ahmed@example.com',
            'SecurePass123!'
        );
        $this->assertTrue($result2['success']);
    }

    /** @test */
    public function it_includes_user_abilities_in_token_response()
    {
        // Create merchant user
        $user = User::factory()->create([
            'phone' => '+966501234567',
            'password' => Hash::make('SecurePass123!'),
            'type' => User::TYPE_MERCHANT,
            'status' => User::STATUS_ACTIVE,
        ]);

        $result = $this->authService->loginWithSecurity(
            '+966501234567',
            'SecurePass123!'
        );

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('abilities', $result);
        $this->assertContains('merchant:read', $result['abilities']);
        $this->assertContains('merchant:write', $result['abilities']);
        $this->assertContains('bids:create', $result['abilities']);
    }

    /** @test */
    public function it_indicates_verification_requirement_in_login_response()
    {
        // Create unverified user
        $user = User::factory()->create([
            'phone' => '+966501234567',
            'password' => Hash::make('SecurePass123!'),
            'status' => User::STATUS_ACTIVE,
            'phone_verified_at' => null, // Not verified
        ]);

        $result = $this->authService->loginWithSecurity(
            '+966501234567',
            'SecurePass123!'
        );

        $this->assertTrue($result['success']);
        $this->assertTrue($result['requires_verification']);
    }
}
