<?php

namespace App\Workflows\Compensation;

use App\Workflows\Activities\BaseRpcActivity;
use App\Models\Payment;
use App\Events\PaymentCancelled;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cancel Payment Record Activity (Compensation)
 * 
 * Cancels payment record when saga fails after record creation.
 * This is an idempotent compensation activity that safely handles
 * cases where the payment record has already been cancelled or doesn't exist.
 */
class CancelPaymentRecordActivity extends BaseRpcActivity
{
    /**
     * Execute the payment record cancellation compensation activity
     *
     * @param array $data Payment data from failed saga step
     * @return array Payment cancellation result
     */
    public function execute(array $data): array
    {
        Log::info("CancelPaymentRecordActivity (compensation) started", [
            'saga_id' => $this->getSagaId(),
            'payment_id' => $data['payment_id'] ?? null,
            'payment_reference' => $data['payment_reference'] ?? null
        ]);

        try {
            // This is a compensation activity, so we need to be very defensive
            $paymentId = $data['payment_id'] ?? null;
            $paymentReference = $data['payment_reference'] ?? null;
            
            if (!$paymentId && !$paymentReference) {
                Log::info("No payment ID or reference provided for cancellation - treating as idempotent success", [
                    'saga_id' => $this->getSagaId()
                ]);
                
                return $this->successResponse([
                    'cancelled' => false,
                    'reason' => 'no_payment_to_cancel',
                    'message' => 'No payment information provided - idempotent operation'
                ]);
            }

            // Find the payment record
            $payment = null;
            if ($paymentId) {
                $payment = Payment::find($paymentId);
            } elseif ($paymentReference) {
                $payment = Payment::where('payment_reference', $paymentReference)->first();
            }

            if (!$payment) {
                Log::info("Payment record not found for cancellation - treating as idempotent success", [
                    'saga_id' => $this->getSagaId(),
                    'payment_id' => $paymentId,
                    'payment_reference' => $paymentReference
                ]);
                
                return $this->successResponse([
                    'cancelled' => false,
                    'reason' => 'payment_not_found',
                    'message' => 'Payment record not found - may have been already cancelled or never created'
                ]);
            }

            // Check if payment is already cancelled
            if ($payment->status === Payment::STATUS_CANCELLED) {
                Log::info("Payment record already cancelled", [
                    'saga_id' => $this->getSagaId(),
                    'payment_id' => $payment->id,
                    'payment_reference' => $payment->payment_reference
                ]);
                
                return $this->successResponse([
                    'payment_id' => $payment->id,
                    'payment_reference' => $payment->payment_reference,
                    'cancelled' => false,
                    'reason' => 'already_cancelled',
                    'message' => 'Payment record already cancelled'
                ]);
            }

            // Check if payment can be cancelled
            $canCancel = $this->canCancelPayment($payment);
            if (!$canCancel['can_cancel']) {
                Log::warning("Payment record cannot be cancelled", [
                    'saga_id' => $this->getSagaId(),
                    'payment_id' => $payment->id,
                    'status' => $payment->status,
                    'reason' => $canCancel['reason']
                ]);
                
                return $this->successResponse([
                    'payment_id' => $payment->id,
                    'payment_reference' => $payment->payment_reference,
                    'cancelled' => false,
                    'reason' => 'cannot_cancel',
                    'message' => $canCancel['reason']
                ]);
            }

            // Perform the cancellation
            $cancellationResult = $this->performPaymentCancellation($payment);

            if ($cancellationResult['success']) {
                Log::info("CancelPaymentRecordActivity completed successfully", [
                    'saga_id' => $this->getSagaId(),
                    'payment_id' => $payment->id,
                    'payment_reference' => $payment->payment_reference
                ]);

                return $this->successResponse([
                    'payment_id' => $payment->id,
                    'payment_reference' => $payment->payment_reference,
                    'cancelled' => true,
                    'cancelled_at' => now()->toISOString(),
                    'previous_status' => $cancellationResult['previous_status']
                ]);
            } else {
                throw new Exception('Payment cancellation failed: ' . $cancellationResult['error']);
            }

        } catch (Exception $e) {
            // In compensation activities, we should not throw exceptions
            $this->logError($e);
            
            Log::error("CancelPaymentRecordActivity failed", [
                'saga_id' => $this->getSagaId(),
                'payment_id' => $data['payment_id'] ?? null,
                'error' => $e->getMessage()
            ]);

            // Return success with failure details to prevent saga from failing
            return $this->successResponse([
                'cancelled' => false,
                'reason' => 'cancellation_failed',
                'error' => $e->getMessage(),
                'message' => 'Payment cancellation failed but saga will continue'
            ]);
        }
    }

    /**
     * Check if payment can be cancelled
     */
    private function canCancelPayment(Payment $payment): array
    {
        // Define which statuses can be cancelled
        $cancellableStatuses = [
            Payment::STATUS_PENDING,
            Payment::STATUS_PROCESSING,
            Payment::STATUS_AUTHORIZED,
            Payment::STATUS_FAILED // Can cancel failed payments to clean up
        ];

        if (!in_array($payment->status, $cancellableStatuses)) {
            return [
                'can_cancel' => false,
                'reason' => "Payment status '{$payment->status}' cannot be cancelled"
            ];
        }

        // Don't cancel completed payments - they should be refunded instead
        if ($payment->status === Payment::STATUS_COMPLETED) {
            return [
                'can_cancel' => false,
                'reason' => 'Completed payments should be refunded, not cancelled'
            ];
        }

        return [
            'can_cancel' => true,
            'reason' => 'Payment can be cancelled'
        ];
    }

    /**
     * Perform the actual payment cancellation
     */
    private function performPaymentCancellation(Payment $payment): array
    {
        DB::beginTransaction();
        try {
            $previousStatus = $payment->status;

            // Update payment status to cancelled
            $payment->update([
                'status' => Payment::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'metadata' => json_encode(array_merge(
                    json_decode($payment->metadata ?? '{}', true),
                    [
                        'cancelled_by_saga' => $this->getSagaId(),
                        'cancelled_at' => now()->toISOString(),
                        'previous_status' => $previousStatus,
                        'cancellation_reason' => 'saga_compensation'
                    ]
                ))
            ]);

            // Fire payment cancelled event
            event(new PaymentCancelled($payment));

            DB::commit();

            return [
                'success' => true,
                'previous_status' => $previousStatus
            ];

        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
