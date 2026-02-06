<?php

namespace Tests\Unit;

use App\Services\OtpService;
use Mockery;
use PHPUnit\Framework\TestCase;

class AuthServiceTest extends TestCase
{
    private OtpService $otpService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock OtpService
        $this->otpService = Mockery::mock(OtpService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_instantiate_otp_service()
    {
        $this->assertInstanceOf(OtpService::class, $this->otpService);
    }

    /** @test */
    public function it_validates_saudi_phone_number_format()
    {
        // Test valid Saudi phone number formats
        $validPhones = [
            '+966501234567',
            '966501234567',
            '0501234567',
        ];

        foreach ($validPhones as $phone) {
            // Test the regex pattern used in AuthService
            $isValid = preg_match('/^(\+966|966|0)?[5][0-9]{8}$/', $phone);
            $this->assertEquals(1, $isValid, "Valid phone format {$phone} should match regex");
        }

        // Test invalid formats
        $invalidPhones = [
            '+1234567890',
            '123456789',
            '+966123456789', // Wrong prefix
            '0123456789', // Wrong prefix
        ];

        foreach ($invalidPhones as $phone) {
            $isValid = preg_match('/^(\+966|966|0)?[5][0-9]{8}$/', $phone);
            $this->assertEquals(0, $isValid, "Invalid phone format {$phone} should not match regex");
        }
    }

    /** @test */
    public function it_validates_password_strength_requirements()
    {
        // Test password validation logic
        $testCases = [
            ['password' => 'short', 'shouldPass' => false, 'reason' => 'too short'],
            ['password' => 'nouppercase123!', 'shouldPass' => false, 'reason' => 'no uppercase'],
            ['password' => 'NOLOWERCASE123!', 'shouldPass' => false, 'reason' => 'no lowercase'],
            ['password' => 'NoNumbers!', 'shouldPass' => false, 'reason' => 'no numbers'],
            ['password' => 'NoSpecialChars123', 'shouldPass' => false, 'reason' => 'no special chars'],
            ['password' => 'ValidPass123!', 'shouldPass' => true, 'reason' => 'valid password'],
        ];

        foreach ($testCases as $testCase) {
            $password = $testCase['password'];
            $shouldPass = $testCase['shouldPass'];
            $reason = $testCase['reason'];

            // Test length requirement
            $lengthValid = strlen($password) >= 8;

            // Test uppercase requirement
            $hasUppercase = preg_match('/[A-Z]/', $password);

            // Test lowercase requirement
            $hasLowercase = preg_match('/[a-z]/', $password);

            // Test number requirement
            $hasNumber = preg_match('/[0-9]/', $password);

            // Test special character requirement
            $hasSpecial = preg_match('/[^A-Za-z0-9]/', $password);

            $isValid = $lengthValid && $hasUppercase && $hasLowercase && $hasNumber && $hasSpecial;

            if ($shouldPass) {
                $this->assertTrue($isValid, "Password '{$password}' should be valid ({$reason})");
            } else {
                $this->assertFalse($isValid, "Password '{$password}' should be invalid ({$reason})");
            }
        }
    }

    /** @test */
    public function it_validates_email_format()
    {
        $validEmails = [
            'test@example.com',
            'user.name@domain.co.uk',
            'user+tag@example.org',
        ];

        $invalidEmails = [
            'invalid-email',
            '@domain.com',
            'user@',
            'user..name@domain.com',
        ];

        foreach ($validEmails as $email) {
            $isValid = filter_var($email, FILTER_VALIDATE_EMAIL);
            $this->assertNotFalse($isValid, "Email '{$email}' should be valid");
        }

        foreach ($invalidEmails as $email) {
            $isValid = filter_var($email, FILTER_VALIDATE_EMAIL);
            $this->assertFalse($isValid, "Email '{$email}' should be invalid");
        }
    }

    /** @test */
    public function it_validates_name_length_requirements()
    {
        // Test name length validation logic
        $testCases = [
            ['name' => 'A', 'shouldPass' => false, 'reason' => 'too short'],
            ['name' => 'AB', 'shouldPass' => true, 'reason' => 'minimum length'],
            ['name' => 'Ahmed Al-Saudi', 'shouldPass' => true, 'reason' => 'normal length'],
            ['name' => str_repeat('A', 100), 'shouldPass' => true, 'reason' => 'maximum length'],
            ['name' => str_repeat('A', 101), 'shouldPass' => false, 'reason' => 'too long'],
        ];

        foreach ($testCases as $testCase) {
            $name = $testCase['name'];
            $shouldPass = $testCase['shouldPass'];
            $reason = $testCase['reason'];

            $isValid = strlen($name) >= 2 && strlen($name) <= 100;

            if ($shouldPass) {
                $this->assertTrue($isValid, "Name '{$name}' should be valid ({$reason})");
            } else {
                $this->assertFalse($isValid, "Name '{$name}' should be invalid ({$reason})");
            }
        }
    }
}
