<?php

namespace App\Workflows\Compensation;

use App\Workflows\Activities\BaseRpcActivity;
use App\Models\Payment;
use App\Events\PaymentRefunded;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reverse Payment Activity (Compensation)
 * 
 * Issues a refund when subsequent steps in the payment saga fail.
 * This is an idempotent compensation activity that safely handles
 * cases where the payment has already been refunded or doesn't exist.
 */
class ReversePaymentActivity extends BaseRpcActivity
{
    /**
     * Execute the payment reversal compensation activity
     *
     * @param array $data Payment data from failed saga step
     * @return array Payment reversal result
     */
    public function execute(array $data): array
    {
        Log::info("ReversePaymentActivity (compensation) started", [
            'saga_id' => $this->getSagaId(),
            'payment_id' => $data['payment_id'] ?? null,
            'payment_reference' => $data['payment_reference'] ?? null
        ]);

        try {
            // This is a compensation activity, so we need to be very defensive
            // and handle cases where data might be missing or incomplete
            
            $paymentId = $data['payment_id'] ?? null;
            $paymentReference = $data['payment_reference'] ?? null;
            
            if (!$paymentId && !$paymentReference) {
                Log::info("No payment ID or reference provided for reversal - treating as idempotent success", [
                    'saga_id' => $this->getSagaId()
                ]);
                
                return $this->successResponse([
                    'reversed' => false,
                    'reason' => 'no_payment_to_reverse',
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
                Log::info("Payment not found for reversal - treating as idempotent success", [
                    'saga_id' => $this->getSagaId(),
                    'payment_id' => $paymentId,
                    'payment_reference' => $paymentReference
                ]);
                
                return $this->successResponse([
                    'reversed' => false,
                    'reason' => 'payment_not_found',
                    'message' => 'Payment not found - may have been already reversed or never created'
                ]);
            }

            // Check if payment is already refunded
            if (in_array($payment->status, [Payment::STATUS_REFUNDED, Payment::STATUS_CANCELLED, Payment::STATUS_VOIDED])) {
                Log::info("Payment already reversed", [
                    'saga_id' => $this->getSagaId(),
                    'payment_id' => $payment->id,
                    'current_status' => $payment->status
                ]);
                
                return $this->successResponse([
                    'payment_id' => $payment->id,
                    'payment_reference' => $payment->payment_reference,
                    'reversed' => false,
                    'reason' => 'already_reversed',
                    'current_status' => $payment->status,
                    'message' => 'Payment already in reversed state'
                ]);
            }

            // Check if payment can be reversed
            $canReverse = $this->canReversePayment($payment);
            if (!$canReverse['can_reverse']) {
                Log::warning("Payment cannot be reversed", [
                    'saga_id' => $this->getSagaId(),
                    'payment_id' => $payment->id,
                    'status' => $payment->status,
                    'reason' => $canReverse['reason']
                ]);
                
                return $this->successResponse([
                    'payment_id' => $payment->id,
                    'payment_reference' => $payment->payment_reference,
                    'reversed' => false,
                    'reason' => 'cannot_reverse',
                    'message' => $canReverse['reason']
                ]);
            }

            // Perform the reversal
            $reversalResult = $this->performPaymentReversal($payment);

            if ($reversalResult['success']) {
                Log::info("ReversePaymentActivity completed successfully", [
                    'saga_id' => $this->getSagaId(),
                    'payment_id' => $payment->id,
                    'reversal_method' => $reversalResult['method']
                ]);

                return $this->successResponse([
                    'payment_id' => $payment->id,
                    'payment_reference' => $payment->payment_reference,
                    'reversed' => true,
                    'reversal_method' => $reversalResult['method'],
                    'reversal_reference' => $reversalResult['reference'],
                    'reversed_at' => now()->toISOString()
                ]);
            } else {
                throw new Exception('Payment reversal failed: ' . $reversalResult['error']);
            }

        } catch (Exception $e) {
            // In compensation activities, we should not throw exceptions
            // Instead, log the error and return a response indicating the issue
            $this->logError($e);
            
            Log::error("ReversePaymentActivity failed", [
                'saga_id' => $this->getSagaId(),
                'payment_id' => $data['payment_id'] ?? null,
                'error' => $e->getMessage()
            ]);

            // Return success with failure details to prevent saga from failing
            return $this->successResponse([
                'reversed' => false,
                'reason' => 'reversal_failed',
                'error' => $e->getMessage(),
                'message' => 'Payment reversal failed but saga will continue'
            ]);
        }
    }

    /**
     * Check if payment can be reversed
     */
    private function canReversePayment(Payment $payment): array
    {
        // Define which statuses can be reversed
        $reversibleStatuses = [
            Payment::STATUS_COMPLETED,
            Payment::STATUS_AUTHORIZED,
            Payment::STATUS_PENDING,
            Payment::STATUS_PROCESSING
        ];

        if (!in_array($payment->status, $reversibleStatuses)) {
            return [
                'can_reverse' => false,
                'reason' => "Payment status '{$payment->status}' cannot be reversed"
            ];
        }

        // Check if payment is too old (some gateways have time limits)
        $paymentAge = now()->diffInDays($payment->created_at);
        if ($paymentAge > 180) { // 6 months
            return [
                'can_reverse' => false,
                'reason' => 'Payment is too old to reverse (over 180 days)'
            ];
        }

        return [
            'can_reverse' => true,
            'reason' => 'Payment can be reversed'
        ];
    }

    /**
     * Perform the actual payment reversal
     */
    private function performPaymentReversal(Payment $payment): array
    {
        DB::beginTransaction();
        try {
            $reversalMethod = $this->determineReversalMethod($payment);
            $reversalReference = 'REV_' . $payment->payment_reference . '_' . time();

            // Update payment status
            $payment->update([
                'status' => Payment::STATUS_REFUNDED,
                'refunded_at' => now(),
                'refund_reference' => $reversalReference,
                'metadata' => json_encode(array_merge(
                    json_decode($payment->metadata ?? '{}', true),
                    [
                        'reversed_by_saga' => $this->getSagaId(),
                        'reversal_method' => $reversalMethod,
                        'reversal_reference' => $reversalReference,
                        'reversed_at' => now()->toISOString()
                    ]
                ))
            ]);

            // Fire payment refunded event
            event(new PaymentRefunded($payment, 'saga_compensation', $payment->amount, 'Saga compensation reversal'));

            DB::commit();

            return [
                'success' => true,
                'method' => $reversalMethod,
                'reference' => $reversalReference
            ];

        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Determine the appropriate reversal method
     */
    private function determineReversalMethod(Payment $payment): string
    {
        switch ($payment->status) {
            case Payment::STATUS_AUTHORIZED:
                return 'void'; // Void authorized payments
                
            case Payment::STATUS_COMPLETED:
                return 'refund'; // Refund completed payments
                
            case Payment::STATUS_PENDING:
            case Payment::STATUS_PROCESSING:
                return 'cancel'; // Cancel pending payments
                
            default:
                return 'refund';
        }
    }
}
