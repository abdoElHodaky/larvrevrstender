<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Workflows\PaymentProcessingSaga;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use App\Events\PaymentInitiated;
use App\Events\PaymentCompleted;
use App\Events\PaymentRefunded;
use App\Events\PaymentCancelled;

class PaymentProcessingSagaTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Fake events for testing
        Event::fake();
        // Queue::fake(); // Commented out to allow workflow activities to execute
    }

    /**
     * Test successful payment processing saga execution
     */
    public function test_successful_payment_processing_saga()
    {
        // Arrange
        $paymentData = [
            'order_id' => 12345,
            'customer_id' => 67890,
            'merchant_id' => 1,
            'amount' => 100.00,
            'currency' => 'SAR',
            'payment_method' => 'credit_card',
            'payment_provider' => 'stripe',
            'card_token' => 'tok_test_123',
            'payment_details' => [
                'card_number' => '4242424242424242',
                'expiry_month' => '12',
                'expiry_year' => '2025',
                'cvv' => '123',
                'cardholder_name' => 'John Doe',
                'card_last_four' => '4242',
                'card_brand' => 'visa'
            ]
        ];

        // Check if workflow tables exist
        $tables = \DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'workflow%'");
        error_log("Workflow tables in test database: " . json_encode($tables));
        
        // Check queue configuration
        error_log("QUEUE_CONNECTION env: " . env('QUEUE_CONNECTION'));
        error_log("Queue default config: " . config('queue.default'));
        
        // Test if sync queue is working
        \App\Jobs\TestJob::dispatch();
        error_log("TestJob dispatched");
        
        // Check if StoredWorkflow exists in database
        $workflows = \Workflow\Models\StoredWorkflow::all();
        error_log("StoredWorkflows in database: " . json_encode($workflows->toArray()));

        // Act
        $workflow = PaymentProcessingSaga::start($paymentData);
        
        // Check workflows after start
        $workflowsAfter = \Workflow\Models\StoredWorkflow::all();
        error_log("StoredWorkflows after start: " . json_encode($workflowsAfter->toArray()));
        
        // Check workflow logs
        $workflowLogs = \Workflow\Models\StoredWorkflowLog::all();
        error_log("WorkflowLogs: " . json_encode($workflowLogs->toArray()));
        
        $result = $workflow->output();

        // Assert
        $this->assertIsArray($result);
        $this->assertTrue($result['success'] ?? false);
        $this->assertArrayHasKey('payment_id', $result);
        $this->assertArrayHasKey('workflow_id', $result);
        
        // Verify payment record was created
        $this->assertDatabaseHas('payments', [
            'order_id' => 12345,
            'customer_id' => 67890,
            'amount' => 100.00,
            'status' => Payment::STATUS_COMPLETED
        ]);

        // Verify events were dispatched
        Event::assertDispatched(PaymentInitiated::class);
        Event::assertDispatched(PaymentCompleted::class);
    }

    /**
     * Test payment processing saga with validation failure
     */
    public function test_payment_processing_saga_validation_failure()
    {
        // Arrange - Invalid payment data
        $paymentData = [
            'order_id' => 99999, // Non-existent order
            'customer_id' => 67890,
            'amount' => -100.00, // Invalid amount
            'currency' => 'INVALID',
            'payment_method' => 'invalid_method'
        ];

        // Act
        $workflow = PaymentProcessingSaga::start($paymentData);
        $result = $workflow->output();

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['success'] ?? true);
        $this->assertArrayHasKey('error', $result);
        
        // Verify no payment record was created
        $this->assertDatabaseMissing('payments', [
            'order_id' => 99999
        ]);

        // Verify no completion events were dispatched
        Event::assertNotDispatched(PaymentCompleted::class);
    }

    /**
     * Test payment processing saga with gateway failure and compensation
     */
    public function test_payment_processing_saga_gateway_failure_with_compensation()
    {
        // This test would require mocking the payment gateway to fail
        // and verifying that compensation activities are executed
        
        // Arrange
        $paymentData = [
            'order_id' => 12345,
            'customer_id' => 67890,
            'merchant_id' => 1,
            'amount' => 100.00,
            'currency' => 'SAR',
            'payment_method' => 'card',
            'payment_provider' => 'stripe',
            'card_token' => 'tok_fail_123', // Token that will cause failure
        ];

        // Mock gateway failure scenario
        // This would require additional setup to mock RPC calls

        $this->markTestIncomplete('Gateway failure testing requires RPC mocking setup');
    }

    /**
     * Test payment processing saga compensation rollback
     */
    public function test_payment_processing_saga_compensation_rollback()
    {
        // This test verifies that when a saga fails after creating a payment record,
        // the compensation activities properly reverse the changes
        
        $this->markTestIncomplete('Compensation testing requires workflow failure simulation');
    }

    /**
     * Test payment processing saga idempotency
     */
    public function test_payment_processing_saga_idempotency()
    {
        // Arrange
        $paymentData = [
            'order_id' => 12345,
            'customer_id' => 67890,
            'merchant_id' => 1,
            'amount' => 100.00,
            'currency' => 'SAR',
            'payment_method' => 'card',
            'payment_provider' => 'stripe',
            'card_token' => 'tok_test_123',
            'idempotency_key' => 'test_key_123'
        ];

        // Act - Run the same saga twice
        $workflow1 = PaymentProcessingSaga::start($paymentData);
        $result1 = $workflow1->output();
        
        $workflow2 = PaymentProcessingSaga::start($paymentData);
        $result2 = $workflow2->output();

        // Assert - Should handle duplicate processing gracefully
        $this->assertIsArray($result1);
        $this->assertIsArray($result2);
        
        // Verify only one payment record exists
        $paymentCount = Payment::where('order_id', 12345)->count();
        $this->assertEquals(1, $paymentCount);
    }

    /**
     * Test payment processing saga with different payment methods
     */
    public function test_payment_processing_saga_different_payment_methods()
    {
        $paymentMethods = [
            'card' => [
                'payment_provider' => 'stripe',
                'card_token' => 'tok_test_123',
                'payment_details' => ['card_last_four' => '4242', 'card_brand' => 'visa']
            ],
            'bank_transfer' => [
                'payment_provider' => 'bank',
                'payment_details' => ['bank_code' => 'RIBL', 'account_number' => '****1234']
            ],
            'wallet' => [
                'payment_provider' => 'stc_pay',
                'payment_details' => ['wallet_id' => 'stc_123456']
            ]
        ];

        foreach ($paymentMethods as $method => $details) {
            // Arrange
            $paymentData = array_merge([
                'order_id' => rand(10000, 99999),
                'customer_id' => 67890,
                'merchant_id' => 1,
                'amount' => 100.00,
                'currency' => 'SAR',
                'payment_method' => $method,
            ], $details);

            // Act
            $workflow = PaymentProcessingSaga::start($paymentData);
            $result = $workflow->output();

            // Assert
            $this->assertIsArray($result, "Failed for payment method: {$method}");
            $this->assertTrue($result['success'] ?? false, "Payment failed for method: {$method}");
            
            // Verify payment record
            $this->assertDatabaseHas('payments', [
                'order_id' => $paymentData['order_id'],
                'payment_method' => $method,
                'status' => Payment::STATUS_COMPLETED
            ]);
        }
    }

    /**
     * Test workflow persistence and recovery
     */
    public function test_workflow_persistence_and_recovery()
    {
        // This test verifies that workflows can be persisted and recovered
        // in case of system failures
        
        // Arrange
        $paymentData = [
            'order_id' => 12345,
            'customer_id' => 67890,
            'merchant_id' => 1,
            'amount' => 100.00,
            'currency' => 'SAR',
            'payment_method' => 'card',
            'payment_provider' => 'stripe',
            'card_token' => 'tok_test_123'
        ];

        // Act
        $workflow = PaymentProcessingSaga::start($paymentData);
        $workflowId = $workflow->id();

        // Assert workflow is persisted
        $this->assertDatabaseHas('workflows', [
            'id' => $workflowId,
            'class' => PaymentProcessingSaga::class,
            'status' => 'completed'
        ]);
    }
}
