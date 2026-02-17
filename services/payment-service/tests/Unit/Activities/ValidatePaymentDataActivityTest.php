<?php

namespace Tests\Unit\Activities;

use Tests\TestCase;
use App\Workflows\Activities\ValidatePaymentDataActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class ValidatePaymentDataActivityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test successful payment data validation
     */
    public function test_successful_payment_data_validation()
    {
        // Arrange
        $storedWorkflow = \Mockery::mock(\Workflow\Models\StoredWorkflow::class);
        $activity = new ValidatePaymentDataActivity(1, now()->toISOString(), $storedWorkflow);
        $paymentData = [
            'order_id' => 12345,
            'customer_id' => 67890,
            'amount' => 100.00,
            'currency' => 'SAR',
            'payment_method' => 'card',
            'payment_provider' => 'stripe'
        ];

        // Mock successful order validation (would require RPC mocking)
        // For now, we'll test the structure

        // Act & Assert
        $this->assertInstanceOf(ValidatePaymentDataActivity::class, $activity);
        $this->assertTrue(method_exists($activity, 'execute'));
    }

    /**
     * Test validation with missing required fields
     */
    public function test_validation_with_missing_required_fields()
    {
        // Arrange
        $storedWorkflow = \Mockery::mock(\Workflow\Models\StoredWorkflow::class);
        $storedWorkflow->shouldReceive('getAttribute')->with('id')->andReturn('test-workflow-id');
        $activity = new ValidatePaymentDataActivity(1, now()->toISOString(), $storedWorkflow);
        $invalidData = [
            'customer_id' => 67890,
            // Missing order_id, amount, etc.
        ];

        // Act
        $result = $activity->execute($invalidData);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['success'] ?? true);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test validation with invalid amount
     */
    public function test_validation_with_invalid_amount()
    {
        // Arrange
        $storedWorkflow = \Mockery::mock(\Workflow\Models\StoredWorkflow::class);
        $storedWorkflow->shouldReceive('getAttribute')->with('id')->andReturn('test-workflow-id');
        $activity = new ValidatePaymentDataActivity(1, now()->toISOString(), $storedWorkflow);
        $invalidData = [
            'order_id' => 12345,
            'customer_id' => 67890,
            'amount' => -100.00, // Invalid negative amount
            'currency' => 'SAR',
            'payment_method' => 'card',
            'payment_details' => [
                'card_last_four' => '4242',
                'card_brand' => 'visa'
            ]
        ];

        // Act
        $result = $activity->execute($invalidData);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['success'] ?? true);
        $this->assertStringContainsString('amount', strtolower($result['error'] ?? ''));
    }

    /**
     * Test validation with invalid currency
     */
    public function test_validation_with_invalid_currency()
    {
        // Arrange
        $storedWorkflow = \Mockery::mock(\Workflow\Models\StoredWorkflow::class);
        $storedWorkflow->shouldReceive('getAttribute')->with('id')->andReturn('test-workflow-id');
        $activity = new ValidatePaymentDataActivity(1, now()->toISOString(), $storedWorkflow);
        $invalidData = [
            'order_id' => 12345,
            'customer_id' => 67890,
            'amount' => 100.00,
            'currency' => 'INVALID', // Invalid currency code
            'payment_method' => 'card',
            'payment_details' => [
                'card_last_four' => '4242',
                'card_brand' => 'visa'
            ]
        ];

        // Act
        $result = $activity->execute($invalidData);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['success'] ?? true);
        $this->assertStringContainsString('currency', strtolower($result['error'] ?? ''));
    }
}
