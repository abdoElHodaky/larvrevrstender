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
        error_log("PaymentProcessingSaga::start called with data: " . json_encode($paymentData));
        $workflow = WorkflowStub::make(static::class);
        error_log("WorkflowStub created: " . get_class($workflow));
        $workflow->start($paymentData);
        error_log("Workflow started, returning stub");
        return $workflow;
    }

    /**
     * Execute the payment processing saga
     *
     * @param array $paymentData Payment data to process
     * @return \Generator Saga execution workflow
     */
    public function execute(array $paymentData)
    {
        error_log("PaymentProcessingSaga execute method called with data: " . json_encode($paymentData));
        Log::info("PaymentProcessingSaga started", [
            'order_id' => $paymentData['order_id'] ?? null,
            'customer_id' => $paymentData['customer_id'] ?? null,
            'amount' => $paymentData['amount'] ?? null,
            'payment_method' => $paymentData['payment_method'] ?? null
        ]);

        try {
            // Step 1: Validate Payment Data
            error_log("About to call ValidatePaymentDataActivity");
            $validationResult = yield activity(ValidatePaymentDataActivity::class, $paymentData);
            error_log("ValidatePaymentDataActivity result: " . json_encode($validationResult));
            error_log("ValidatePaymentDataActivity result type: " . gettype($validationResult));
            
            // Check if validation failed
            if (!($validationResult['success'] ?? false)) {
                $errorMessage = $validationResult['error'] ?? 'Payment validation failed';
                Log::error("Payment validation failed, stopping saga", [
                    'saga_id' => $this->workflowId(),
                    'error' => $errorMessage,
                    'validation_result' => $validationResult
                ]);
                throw new Exception($errorMessage);
            }
            
            $this->addCompensation(fn() => activity(RestoreOrderStatusActivity::class, $validationResult));

            // Step 2: Process Payment
            $processingResult = yield activity(ProcessPaymentActivity::class, array_merge($paymentData, $validationResult));
            $this->addCompensation(fn() => activity(RestoreOrderStatusActivity::class, $processingResult));

            // Step 3: Create Payment Record
            $recordResult = yield activity(CreatePaymentRecordActivity::class, array_merge($paymentData, $validationResult, $processingResult));
            $this->addCompensation(fn() => activity(ReversePaymentActivity::class, $recordResult));
            $this->addCompensation(fn() => activity(RestoreOrderStatusActivity::class, $recordResult));

            // Step 4: Confirm Payment
            $confirmationResult = yield activity(ConfirmPaymentActivity::class, array_merge($paymentData, $validationResult, $processingResult, $recordResult));
            $this->addCompensation(fn() => activity(CancelPaymentRecordActivity::class, $confirmationResult));
            $this->addCompensation(fn() => activity(ReversePaymentActivity::class, $confirmationResult));
            $this->addCompensation(fn() => activity(RestoreOrderStatusActivity::class, $confirmationResult));

            // Step 5: Update Order Status
            $orderUpdateResult = yield activity(UpdateOrderStatusActivity::class, array_merge($paymentData, $validationResult, $processingResult, $recordResult, $confirmationResult));
            $this->addCompensation(fn() => activity(RestoreOrderStatusActivity::class, $orderUpdateResult));
            $this->addCompensation(fn() => activity(CancelPaymentRecordActivity::class, $orderUpdateResult));
            $this->addCompensation(fn() => activity(ReversePaymentActivity::class, $orderUpdateResult));

            // Saga completed successfully
            $finalResult = array_merge(
                $validationResult,
                $processingResult,
                $recordResult,
                $confirmationResult,
                $orderUpdateResult
            );

            Log::info("PaymentProcessingSaga completed successfully", [
                'payment_id' => $finalResult['payment_id'] ?? null,
                'payment_reference' => $finalResult['payment_reference'] ?? null,
                'order_id' => $finalResult['order_id'] ?? null,
                'amount' => $finalResult['amount'] ?? null
            ]);

            return $finalResult;

        } catch (\Throwable $e) {
            Log::error("PaymentProcessingSaga encountered error, executing compensations", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Execute compensations in reverse order
            yield from $this->compensate();
            
            // Return error response instead of throwing exception
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'saga_id' => $this->workflowId(),
                'timestamp' => now()->toISOString(),
                'compensations_executed' => true
            ];
        }
    }


}
