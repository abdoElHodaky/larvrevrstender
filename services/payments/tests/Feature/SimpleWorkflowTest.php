<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Workflows\PaymentProcessingSaga;
use App\Workflows\SimplePaymentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Workflow\WorkflowStub;

class SimpleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_can_be_created()
    {
        $paymentData = [
            'order_id' => 12345,
            'customer_id' => 67890,
            'amount' => 100.00,
            'currency' => 'USD',
            'payment_method' => 'card',
            'payment_details' => [
                'card_number' => '4111111111111111',
                'expiry_month' => '12',
                'expiry_year' => '2025',
                'cvv' => '123'
            ]
        ];

        // Just test that we can create a workflow stub
        $workflow = WorkflowStub::make(PaymentProcessingSaga::class);
        $this->assertNotNull($workflow);
        
        // Test that we can call the static start method
        $workflowFromStatic = PaymentProcessingSaga::start($paymentData);
        $this->assertNotNull($workflowFromStatic);
    }

    public function test_simple_workflow_execution()
    {
        $this->markTestSkipped('Workflow execution requires a worker process in CI/CD environment');
        
        $paymentData = [
            'order_id' => 12345,
            'amount' => 100.00,
        ];

        // Test the simple workflow
        $workflow = SimplePaymentWorkflow::start($paymentData);
        
        // Wait for the workflow to complete
        while ($workflow->running());
        
        $result = $workflow->output();
        
        $this->assertNotNull($result, 'Workflow output should not be null');
        $this->assertIsArray($result);
        $this->assertTrue($result['success'] ?? false);
        $this->assertArrayHasKey('workflow_id', $result);
        $this->assertArrayHasKey('validation', $result);
    }
}
