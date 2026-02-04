<?php

namespace Tests\Unit;

use App\Models\CustomerProfile;
use App\Models\MerchantProfile;
use App\Models\Address;
use App\Models\Vehicle;
use App\Services\EnhancedUserService;
use App\Events\UserProfileUpdated;
use App\Events\UserVerificationStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EnhancedUserServiceTest extends TestCase
{
    use RefreshDatabase;

    private EnhancedUserService $userService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userService = new EnhancedUserService();
        Event::fake();
    }

    /** @test */
    public function it_can_create_customer_profile_successfully()
    {
        $userId = 1;
        $profileData = [
            'national_id' => '1234567890',
            'national_address' => '123 King Fahd Road, Riyadh',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'occupation' => 'Software Engineer',
            'company_name' => 'Tech Corp',
            'industry' => 'Technology',
            'company_size' => 'medium',
            'annual_revenue' => 500000,
            'default_location' => [
                'latitude' => 24.7136,
                'longitude' => 46.6753,
                'address' => 'Riyadh, Saudi Arabia'
            ],
        ];

        $result = $this->userService->createOrUpdateProfile($userId, 'customer', $profileData);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('profile', $result);
        $this->assertEquals('Profile updated successfully', $result['message']);

        // Verify profile was created in database
        $this->assertDatabaseHas('customer_profiles', [
            'user_id' => $userId,
            'national_id' => '1234567890',
            'occupation' => 'Software Engineer',
            'company_name' => 'Tech Corp',
            'industry' => 'Technology',
        ]);

        $profile = CustomerProfile::where('user_id', $userId)->first();
        $this->assertEquals('pending', $profile->verification_status);
        $this->assertArrayHasKey('language', $profile->preferences);
        $this->assertEquals('ar', $profile->preferences['language']);
    }

    /** @test */
    public function it_can_create_merchant_profile_successfully()
    {
        $userId = 2;
        $profileData = [
            'business_name' => 'Auto Parts Saudi',
            'business_type' => 'automotive',
            'commercial_registration' => 'CR123456789',
            'tax_number' => 'VAT987654321',
            'business_address' => '456 Industrial Street, Jeddah',
            'contact_person' => 'Ahmed Al-Saudi',
            'contact_phone' => '+966501234567',
            'contact_email' => 'ahmed@autoparts.sa',
            'business_description' => 'Leading automotive parts supplier',
            'service_areas' => ['Riyadh', 'Jeddah', 'Dammam'],
            'specializations' => ['Engine Parts', 'Brake Systems'],
            'certifications' => ['ISO 9001', 'SASO'],
        ];

        $result = $this->userService->createOrUpdateProfile($userId, 'merchant', $profileData);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('profile', $result);

        // Verify profile was created in database
        $this->assertDatabaseHas('merchant_profiles', [
            'user_id' => $userId,
            'business_name' => 'Auto Parts Saudi',
            'commercial_registration' => 'CR123456789',
            'tax_number' => 'VAT987654321',
        ]);

        $profile = MerchantProfile::where('user_id', $userId)->first();
        $this->assertEquals('pending', $profile->verification_status);
        $this->assertContains('Riyadh', $profile->service_areas);
        $this->assertContains('Engine Parts', $profile->specializations);
    }

    /** @test */
    public function it_can_get_user_profile_with_caching()
    {
        // Create a customer profile
        $profile = CustomerProfile::factory()->create([
            'user_id' => 1,
            'national_id' => '1234567890',
            'verification_status' => 'approved',
        ]);

        $result = $this->userService->getUserProfile(1, 'customer');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('profile', $result);
        $this->assertEquals('approved', $result['verification_status']);
        $this->assertIsInt($result['completion_percentage']);

        // Verify caching
        $this->assertTrue(Cache::has("user_profile:1"));
    }

    /** @test */
    public function it_returns_error_for_non_existent_profile()
    {
        $result = $this->userService->getUserProfile(999, 'customer');

        $this->assertFalse($result['success']);
        $this->assertEquals('Profile not found', $result['message']);
    }

    /** @test */
    public function it_can_update_user_preferences()
    {
        $profile = CustomerProfile::factory()->create([
            'user_id' => 1,
            'preferences' => [
                'language' => 'ar',
                'currency' => 'SAR',
                'notifications' => true,
            ],
        ]);

        $newPreferences = [
            'language' => 'en',
            'email_updates' => false,
            'sms_updates' => true,
        ];

        $result = $this->userService->updateUserPreferences(1, 'customer', $newPreferences);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('preferences', $result);

        $profile->refresh();
        $this->assertEquals('en', $profile->preferences['language']);
        $this->assertEquals('SAR', $profile->preferences['currency']); // Should be preserved
        $this->assertFalse($profile->preferences['email_updates']);
        $this->assertTrue($profile->preferences['sms_updates']);

        Event::assertDispatched(UserProfileUpdated::class);
    }

    /** @test */
    public function it_validates_preferences_keys()
    {
        $profile = CustomerProfile::factory()->create(['user_id' => 1]);

        $invalidPreferences = [
            'language' => 'en',
            'invalid_key' => 'should_be_filtered',
            'notifications' => true,
            'another_invalid' => 'filtered',
        ];

        $result = $this->userService->updateUserPreferences(1, 'customer', $invalidPreferences);

        $this->assertTrue($result['success']);
        
        $profile->refresh();
        $this->assertEquals('en', $profile->preferences['language']);
        $this->assertTrue($profile->preferences['notifications']);
        $this->assertArrayNotHasKey('invalid_key', $profile->preferences);
        $this->assertArrayNotHasKey('another_invalid', $profile->preferences);
    }

    /** @test */
    public function it_can_add_user_address()
    {
        $profile = CustomerProfile::factory()->create(['user_id' => 1]);

        $addressData = [
            'type' => 'delivery',
            'label' => 'Home',
            'street_address' => '123 King Fahd Road',
            'city' => 'Riyadh',
            'state' => 'Riyadh Province',
            'postal_code' => '12345',
            'country' => 'SA',
            'latitude' => 24.7136,
            'longitude' => 46.6753,
            'is_default' => true,
        ];

        $result = $this->userService->addUserAddress(1, 'customer', $addressData);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('address', $result);
        $this->assertEquals('Address added successfully', $result['message']);

        // Verify address was created
        $this->assertDatabaseHas('addresses', [
            'user_id' => 1,
            'type' => 'delivery',
            'street_address' => '123 King Fahd Road',
            'city' => 'Riyadh',
            'is_default' => true,
        ]);
    }

    /** @test */
    public function it_validates_address_data()
    {
        $profile = CustomerProfile::factory()->create(['user_id' => 1]);

        $invalidAddressData = [
            'type' => 'delivery',
            'label' => 'Home',
            // Missing required street_address
            'city' => 'Riyadh',
        ];

        $result = $this->userService->addUserAddress(1, 'customer', $invalidAddressData);

        $this->assertFalse($result['success']);
        $this->assertStringContains('street_address is required', $result['message']);
    }

    /** @test */
    public function it_can_add_user_vehicle()
    {
        $profile = CustomerProfile::factory()->create(['user_id' => 1]);

        $vehicleData = [
            'vin' => '1HGBH41JXMN109186',
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2023,
            'color' => 'White',
            'license_plate' => 'ABC-1234',
            'engine_type' => '2.5L I4',
            'transmission' => 'Automatic',
            'mileage' => 15000,
            'is_primary' => true,
        ];

        $result = $this->userService->addUserVehicle(1, $vehicleData);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('vehicle', $result);
        $this->assertEquals('Vehicle added successfully', $result['message']);

        // Verify vehicle was created
        $this->assertDatabaseHas('vehicles', [
            'customer_id' => $profile->id,
            'vin' => '1HGBH41JXMN109186',
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2023,
            'is_primary' => true,
        ]);
    }

    /** @test */
    public function it_validates_vehicle_data()
    {
        $profile = CustomerProfile::factory()->create(['user_id' => 1]);

        $invalidVehicleData = [
            'vin' => 'INVALID_VIN', // Invalid VIN format
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2023,
        ];

        $result = $this->userService->addUserVehicle(1, $invalidVehicleData);

        $this->assertFalse($result['success']);
        $this->assertStringContains('Invalid VIN format', $result['message']);
    }

    /** @test */
    public function it_validates_vehicle_year()
    {
        $profile = CustomerProfile::factory()->create(['user_id' => 1]);

        $invalidVehicleData = [
            'vin' => '1HGBH41JXMN109186',
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 1800, // Invalid year
        ];

        $result = $this->userService->addUserVehicle(1, $invalidVehicleData);

        $this->assertFalse($result['success']);
        $this->assertStringContains('Invalid vehicle year', $result['message']);
    }

    /** @test */
    public function it_can_submit_kyc_verification()
    {
        $profile = CustomerProfile::factory()->create(['user_id' => 1]);

        $documents = [
            'national_id' => [
                'file_path' => '/uploads/national_id_123.jpg',
                'file_type' => 'image/jpeg',
                'uploaded_at' => now()->toISOString(),
            ],
            'proof_of_address' => [
                'file_path' => '/uploads/address_proof_123.pdf',
                'file_type' => 'application/pdf',
                'uploaded_at' => now()->toISOString(),
            ],
        ];

        $result = $this->userService->submitKYCVerification(1, 'customer', $documents);

        $this->assertTrue($result['success']);
        $this->assertEquals('pending', $result['verification_status']);
        $this->assertEquals('KYC verification submitted successfully', $result['message']);

        // Verify profile was updated
        $profile->refresh();
        $this->assertEquals('pending', $profile->verification_status);
        $this->assertNotNull($profile->verification_documents);
        $this->assertNotNull($profile->verification_submitted_at);

        Event::assertDispatched(UserVerificationStatusChanged::class);
    }

    /** @test */
    public function it_validates_kyc_documents()
    {
        $profile = CustomerProfile::factory()->create(['user_id' => 1]);

        $invalidDocuments = [
            'national_id' => [
                // Missing file_path
                'file_type' => 'image/jpeg',
            ],
        ];

        $result = $this->userService->submitKYCVerification(1, 'customer', $invalidDocuments);

        $this->assertFalse($result['success']);
        $this->assertStringContains('Document proof_of_address is required', $result['message']);
    }

    /** @test */
    public function it_can_update_verification_status()
    {
        $profile = CustomerProfile::factory()->create([
            'user_id' => 1,
            'verification_status' => 'pending',
        ]);

        $result = $this->userService->updateVerificationStatus(
            1, 
            'customer', 
            'approved', 
            'All documents verified successfully'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('approved', $result['verification_status']);
        $this->assertEquals('Verification status updated successfully', $result['message']);

        // Verify profile was updated
        $profile->refresh();
        $this->assertEquals('approved', $profile->verification_status);
        $this->assertEquals('All documents verified successfully', $profile->verification_notes);
        $this->assertNotNull($profile->verification_updated_at);

        Event::assertDispatched(UserVerificationStatusChanged::class);
    }

    /** @test */
    public function it_validates_verification_status()
    {
        $profile = CustomerProfile::factory()->create(['user_id' => 1]);

        $result = $this->userService->updateVerificationStatus(1, 'customer', 'invalid_status');

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid verification status', $result['message']);
    }

    /** @test */
    public function it_can_get_user_activity_summary()
    {
        $result = $this->userService->getUserActivitySummary(1);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('activity', $result);
        $this->assertArrayHasKey('orders_count', $result['activity']);
        $this->assertArrayHasKey('bids_count', $result['activity']);
        $this->assertArrayHasKey('profile_completion', $result['activity']);
    }

    /** @test */
    public function it_can_search_users_with_filters()
    {
        // Create test profiles
        CustomerProfile::factory()->create([
            'user_id' => 1,
            'verification_status' => 'approved',
            'industry' => 'Technology',
            'company_size' => 'medium',
        ]);

        CustomerProfile::factory()->create([
            'user_id' => 2,
            'verification_status' => 'pending',
            'industry' => 'Healthcare',
            'company_size' => 'large',
        ]);

        $criteria = [
            'verification_status' => 'approved',
            'industry' => 'Technology',
            'page' => 1,
            'per_page' => 10,
        ];

        $result = $this->userService->searchUsers($criteria);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('users', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertCount(1, $result['users']);
        $this->assertEquals('approved', $result['users'][0]->verification_status);
        $this->assertEquals('Technology', $result['users'][0]->industry);
    }

    /** @test */
    public function it_calculates_profile_completion_percentage()
    {
        // Create incomplete profile
        $incompleteProfile = CustomerProfile::factory()->create([
            'user_id' => 1,
            'national_id' => '1234567890',
            'national_address' => null, // Missing
            'date_of_birth' => '1990-01-01',
            'occupation' => null, // Missing
            'default_location' => ['lat' => 24.7136, 'lng' => 46.6753],
        ]);

        $result = $this->userService->getUserProfile(1, 'customer');

        $this->assertTrue($result['success']);
        // Should be 60% (3 out of 5 required fields completed)
        $this->assertEquals(60, $result['completion_percentage']);

        // Create complete profile
        $completeProfile = CustomerProfile::factory()->create([
            'user_id' => 2,
            'national_id' => '1234567890',
            'national_address' => '123 King Fahd Road',
            'date_of_birth' => '1990-01-01',
            'occupation' => 'Engineer',
            'default_location' => ['lat' => 24.7136, 'lng' => 46.6753],
        ]);

        $result = $this->userService->getUserProfile(2, 'customer');

        $this->assertTrue($result['success']);
        $this->assertEquals(100, $result['completion_percentage']);
    }

    /** @test */
    public function it_handles_invalid_user_type()
    {
        $result = $this->userService->createOrUpdateProfile(1, 'invalid_type', []);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid user type', $result['message']);
    }

    /** @test */
    public function it_clears_cache_after_profile_updates()
    {
        $profile = CustomerProfile::factory()->create(['user_id' => 1]);

        // First call to populate cache
        $this->userService->getUserProfile(1, 'customer');
        $this->assertTrue(Cache::has("user_profile:1"));

        // Update profile should clear cache
        $this->userService->updateUserPreferences(1, 'customer', ['language' => 'en']);
        $this->assertFalse(Cache::has("user_profile:1"));
    }

    /** @test */
    public function it_sets_default_address_correctly()
    {
        $profile = CustomerProfile::factory()->create(['user_id' => 1]);

        // Add first address as default
        $this->userService->addUserAddress(1, 'customer', [
            'street_address' => '123 First Street',
            'city' => 'Riyadh',
            'is_default' => true,
        ]);

        // Add second address as default (should unset first)
        $this->userService->addUserAddress(1, 'customer', [
            'street_address' => '456 Second Street',
            'city' => 'Jeddah',
            'is_default' => true,
        ]);

        // Verify only second address is default
        $this->assertDatabaseHas('addresses', [
            'user_id' => 1,
            'street_address' => '123 First Street',
            'is_default' => false,
        ]);

        $this->assertDatabaseHas('addresses', [
            'user_id' => 1,
            'street_address' => '456 Second Street',
            'is_default' => true,
        ]);
    }

    /** @test */
    public function it_sets_primary_vehicle_correctly()
    {
        $profile = CustomerProfile::factory()->create(['user_id' => 1]);

        // Add first vehicle as primary
        $this->userService->addUserVehicle(1, [
            'vin' => '1HGBH41JXMN109186',
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2023,
            'is_primary' => true,
        ]);

        // Add second vehicle as primary (should unset first)
        $this->userService->addUserVehicle(1, [
            'vin' => '2HGBH41JXMN109187',
            'make' => 'Honda',
            'model' => 'Accord',
            'year' => 2023,
            'is_primary' => true,
        ]);

        // Verify only second vehicle is primary
        $this->assertDatabaseHas('vehicles', [
            'customer_id' => $profile->id,
            'vin' => '1HGBH41JXMN109186',
            'is_primary' => false,
        ]);

        $this->assertDatabaseHas('vehicles', [
            'customer_id' => $profile->id,
            'vin' => '2HGBH41JXMN109187',
            'is_primary' => true,
        ]);
    }
}
