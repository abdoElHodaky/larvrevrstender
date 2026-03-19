<?php

namespace App\Workflows;

use App\Workflows\Activities\ValidatePaymentDataActivity;
use App\Workflows\Activities\ProcessPaymentActivity;
use App\Workflows\Activities\CreatePaymentRecordActivity;
use App\Workflows\Activities\ConfirmPaymentActivity;
use App\Workflows\Activities\UpdateOrderStatusActivity;
use App\Workflows\Compensation\ReversePaymentActivity;
use App\Workflows\Compensation\CancelPaymentRecordActivity;
use App\Workflows\Compensation\RestoreOrderStatusActivity;
use Exception;
use Illuminate\Support\Facades\Log;
use Workflow\Workflow;
use Workflow\WorkflowStub;
use Workflow\ActivityOptions;
use Workflow\RetryOptions;
use function Workflow\activity;

/**
 * Payment Processing Saga
 * 
 * Orchestrates the complete payment processing workflow with automatic compensation
 * on failures. This saga ensures consistency across payment processing, order updates,
 * and fund management through a series of activities and their compensations.
 * 
 * Workflow Steps:
 * 1. ValidatePaymentDataActivity - Validate payment data and gateway availability
 * 2. ProcessPaymentActivity - Process payment through selected gateway
 * 3. CreatePaymentRecordActivity - Create payment record in database
 * 4. ConfirmPaymentActivity - Confirm and complete the payment
 * 5. UpdateOrderStatusActivity - Update order status to reflect payment
 * 
 * Compensation Chain (executed in reverse order on failure):
 * 1. RestoreOrderStatusActivity - Restore original order status
 * 2. CancelPaymentRecordActivity - Cancel payment record
 * 3. ReversePaymentActivity - Reverse/refund the payment
 */
class PaymentProcessingSaga extends Workflow
{
    /**
     * Start the payment processing saga
     *
     * @param array $paymentData Payment data to process
     * @return WorkflowStub Workflow stub for monitoring and control
     */
    public static function start(array $paymentData): WorkflowStub
    {
        $workflow = WorkflowStub::make(static::class);
        $workflow->start($paymentData);
        return $workflow;
    }

    /**
     * Execute the payment processing saga
     *
     * @param array $paymentData Payment data to process
     * @return array Final result of the saga execution
     */
    public function execute(array $paymentData = [])
    {
        $workflowId = $this->uniqueId();
        
        // Use provided data or fall back to workflow input
        $data = !empty($paymentData) ? $paymentData : $this->input();
        
        Log::info("PaymentProcessingSaga started", [
            'workflow_id' => $workflowId,
            'order_id' => $data['order_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'amount' => $data['amount'] ?? null,
            'payment_method' => $data['payment_method'] ?? null
        ]);

        // Configure activity options with retry policies and timeouts
        $standardActivityOptions = ActivityOptions::new()
            ->withRetryOptions(
                RetryOptions::new()
                    ->withMaximumAttempts(3)
                    ->withInitialInterval(1) // 1 second
                    ->withMaximumInterval(60) // 1 minute
                    ->withBackoffCoefficient(2.0) // Exponential backoff
            )
            ->withStartToCloseTimeout(120); // 2 minutes

        $criticalActivityOptions = ActivityOptions::new()
            ->withRetryOptions(
                RetryOptions::new()
                    ->withMaximumAttempts(5)
                    ->withInitialInterval(2) // 2 seconds
                    ->withMaximumInterval(300) // 5 minutes
                    ->withBackoffCoefficient(2.0)
            )
            ->withStartToCloseTimeout(300); // 5 minutes

        try {
            // Step 1: Validate Payment Data
            $validationResult = yield activity(ValidatePaymentDataActivity::class, $data);
            // No compensation needed for validation step

            // Step 2: Process Payment (Critical - higher retry count)
            $processingResult = yield activity(ProcessPaymentActivity::class, array_merge($data, $validationResult));
            $this->addCompensation(fn() => activity(ReversePaymentActivity::class, $processingResult));

            // Step 3: Create Payment Record
            $recordResult = yield activity(CreatePaymentRecordActivity::class, array_merge($data, $validationResult, $processingResult));
            $this->addCompensation(fn() => activity(CancelPaymentRecordActivity::class, $recordResult));

            // Step 4: Confirm Payment (Critical - higher retry count)
            $confirmationResult = yield activity(ConfirmPaymentActivity::class, array_merge($data, $validationResult, $processingResult, $recordResult));
            // Confirmation compensation is handled by previous steps

            // Step 5: Update Order Status
            $orderUpdateResult = yield activity(UpdateOrderStatusActivity::class, array_merge($data, $validationResult, $processingResult, $recordResult, $confirmationResult));
            $this->addCompensation(fn() => activity(RestoreOrderStatusActivity::class, $orderUpdateResult));

            // Saga completed successfully
            $finalResult = array_merge(
                $validationResult,
                $processingResult,
                $recordResult,
                $confirmationResult,
                $orderUpdateResult
            );

            Log::info("PaymentProcessingSaga completed successfully", [
                'workflow_id' => $workflowId,
                'payment_id' => $finalResult['payment_id'] ?? null,
                'payment_reference' => $finalResult['payment_reference'] ?? null,
                'order_id' => $finalResult['order_id'] ?? null,
                'amount' => $finalResult['amount'] ?? null
            ]);

            return $finalResult;

        } catch (\Throwable $e) {
            Log::error("PaymentProcessingSaga encountered error, executing compensations", [
                'workflow_id' => $workflowId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Execute compensations in reverse order
            yield from $this->compensate();
            
            throw $e;
        }
    }


}
