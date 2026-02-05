<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuthService;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Mockery;

class AuthServiceIntegrationTest extends TestCase
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
        $this->assertStringContainsString('reset code sent', $result['message']);
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
}
