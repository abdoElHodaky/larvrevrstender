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
use Workflow\Activity;
use Workflow\Saga;
use Workflow\SagaInterface;

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
class PaymentProcessingSaga extends Saga implements SagaInterface
{
    /**
     * Execute the payment processing saga
     *
     * @param array $paymentData Payment processing data
     * @return array Saga execution result
     */
    public function execute(array $paymentData): array
    {
        $sagaId = $this->getSagaId();
        
        Log::info("PaymentProcessingSaga started", [
            'saga_id' => $sagaId,
            'order_id' => $paymentData['order_id'] ?? null,
            'customer_id' => $paymentData['customer_id'] ?? null,
            'amount' => $paymentData['amount'] ?? null,
            'payment_method' => $paymentData['payment_method'] ?? null
        ]);

        try {
            // Step 1: Validate Payment Data
            $validationResult = $this->executeActivity(
                ValidatePaymentDataActivity::class,
                $paymentData,
                [RestoreOrderStatusActivity::class] // No compensation needed for validation
            );

            if (!$validationResult['success']) {
                Log::error("PaymentProcessingSaga failed at validation", [
                    'saga_id' => $sagaId,
                    'error' => $validationResult['error'] ?? 'Validation failed'
                ]);
                return $this->sagaFailure('Payment validation failed', $validationResult['error'] ?? 'Unknown validation error');
            }

            // Step 2: Process Payment
            $processingResult = $this->executeActivity(
                ProcessPaymentActivity::class,
                array_merge($paymentData, $validationResult['data']),
                [RestoreOrderStatusActivity::class] // Only order restoration needed
            );

            if (!$processingResult['success']) {
                Log::error("PaymentProcessingSaga failed at processing", [
                    'saga_id' => $sagaId,
                    'error' => $processingResult['error'] ?? 'Processing failed'
                ]);
                return $this->sagaFailure('Payment processing failed', $processingResult['error'] ?? 'Unknown processing error');
            }

            // Step 3: Create Payment Record
            $recordResult = $this->executeActivity(
                CreatePaymentRecordActivity::class,
                array_merge($paymentData, $validationResult['data'], $processingResult['data']),
                [
                    ReversePaymentActivity::class,
                    RestoreOrderStatusActivity::class
                ]
            );

            if (!$recordResult['success']) {
                Log::error("PaymentProcessingSaga failed at record creation", [
                    'saga_id' => $sagaId,
                    'error' => $recordResult['error'] ?? 'Record creation failed'
                ]);
                return $this->sagaFailure('Payment record creation failed', $recordResult['error'] ?? 'Unknown record error');
            }

            // Step 4: Confirm Payment
            $confirmationResult = $this->executeActivity(
                ConfirmPaymentActivity::class,
                array_merge($paymentData, $validationResult['data'], $processingResult['data'], $recordResult['data']),
                [
                    CancelPaymentRecordActivity::class,
                    ReversePaymentActivity::class,
                    RestoreOrderStatusActivity::class
                ]
            );

            if (!$confirmationResult['success']) {
                Log::error("PaymentProcessingSaga failed at confirmation", [
                    'saga_id' => $sagaId,
                    'error' => $confirmationResult['error'] ?? 'Confirmation failed'
                ]);
                return $this->sagaFailure('Payment confirmation failed', $confirmationResult['error'] ?? 'Unknown confirmation error');
            }

            // Step 5: Update Order Status
            $orderUpdateResult = $this->executeActivity(
                UpdateOrderStatusActivity::class,
                array_merge($paymentData, $validationResult['data'], $processingResult['data'], $recordResult['data'], $confirmationResult['data']),
                [
                    RestoreOrderStatusActivity::class,
                    CancelPaymentRecordActivity::class,
                    ReversePaymentActivity::class
                ]
            );

            if (!$orderUpdateResult['success']) {
                Log::error("PaymentProcessingSaga failed at order update", [
                    'saga_id' => $sagaId,
                    'error' => $orderUpdateResult['error'] ?? 'Order update failed'
                ]);
                return $this->sagaFailure('Order status update failed', $orderUpdateResult['error'] ?? 'Unknown order update error');
            }

            // Saga completed successfully
            $finalResult = array_merge(
                $validationResult['data'],
                $processingResult['data'],
                $recordResult['data'],
                $confirmationResult['data'],
                $orderUpdateResult['data']
            );

            Log::info("PaymentProcessingSaga completed successfully", [
                'saga_id' => $sagaId,
                'payment_id' => $finalResult['payment_id'] ?? null,
                'payment_reference' => $finalResult['payment_reference'] ?? null,
                'order_id' => $finalResult['order_id'] ?? null,
                'amount' => $finalResult['amount'] ?? null
            ]);

            return $this->sagaSuccess($finalResult);

        } catch (Exception $e) {
            Log::error("PaymentProcessingSaga encountered unexpected error", [
                'saga_id' => $sagaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->sagaFailure('Unexpected saga error', $e->getMessage());
        }
    }

    /**
     * Execute an activity with compensation registration
     */
    private function executeActivity(string $activityClass, array $data, array $compensations = []): array
    {
        try {
            // Inject saga context into activity data
            $data['saga_id'] = $this->getSagaId();
            $data['saga_step'] = class_basename($activityClass);

            // Execute the activity
            $activity = new $activityClass();
            $result = $activity->execute($data);

            // Register compensations for this step (in reverse order)
            foreach (array_reverse($compensations) as $compensationClass) {
                $this->addCompensation($compensationClass, array_merge($data, $result['data'] ?? []));
            }

            return $result;

        } catch (Exception $e) {
            Log::error("Activity execution failed", [
                'saga_id' => $this->getSagaId(),
                'activity' => $activityClass,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * Handle saga success
     */
    private function sagaSuccess(array $data): array
    {
        return [
            'success' => true,
            'saga_id' => $this->getSagaId(),
            'message' => 'Payment processing completed successfully',
            'data' => $data
        ];
    }

    /**
     * Handle saga failure and trigger compensations
     */
    private function sagaFailure(string $message, string $error): array
    {
        // Execute all registered compensations
        $this->executeCompensations();

        return [
            'success' => false,
            'saga_id' => $this->getSagaId(),
            'message' => $message,
            'error' => $error,
            'data' => []
        ];
    }

    /**
     * Get the saga identifier
     */
    private function getSagaId(): string
    {
        return $this->sagaId ?? 'payment-saga-' . uniqid();
    }

    /**
     * Execute compensation activities
     */
    private function executeCompensations(): void
    {
        foreach ($this->compensations as $compensation) {
            try {
                $compensationActivity = new $compensation['class']();
                $result = $compensationActivity->execute($compensation['data']);

                Log::info("Compensation executed", [
                    'saga_id' => $this->getSagaId(),
                    'compensation' => $compensation['class'],
                    'success' => $result['success'] ?? false
                ]);

            } catch (Exception $e) {
                // Compensations should never throw exceptions, but log if they do
                Log::error("Compensation execution failed", [
                    'saga_id' => $this->getSagaId(),
                    'compensation' => $compensation['class'],
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Add compensation activity
     */
    private function addCompensation(string $compensationClass, array $data): void
    {
        $this->compensations[] = [
            'class' => $compensationClass,
            'data' => $data
        ];
    }

    /**
     * Saga compensations registry
     */
    private array $compensations = [];

    /**
     * Saga identifier
     */
    private ?string $sagaId = null;

    /**
     * Set saga identifier
     */
    public function setSagaId(string $sagaId): void
    {
        $this->sagaId = $sagaId;
    }
}
