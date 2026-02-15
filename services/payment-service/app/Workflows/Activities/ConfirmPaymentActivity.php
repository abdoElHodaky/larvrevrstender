<?php

namespace App\Workflows\Activities;

use App\Models\Payment;
use App\Events\PaymentCompleted;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Confirm Payment Activity
 * 
 * Confirms payment and marks it as completed.
 * This step finalizes the payment process and triggers completion events.
 */
class ConfirmPaymentActivity extends BaseRpcActivity
{
    /**
     * Execute the payment confirmation activity
     *
     * @param array $data Payment data from record creation step
     * @return array Payment confirmation result
     * @throws Exception
     */
    public function execute(array $data): array
    {
        Log::info("ConfirmPaymentActivity started", [
            'saga_id' => $this->getSagaId(),
            'payment_id' => $data['payment_id'] ?? null,
            'payment_reference' => $data['payment_reference'] ?? null
        ]);

        DB::beginTransaction();
        try {
            // Validate required fields from previous step
            $this->validateData($data, [
                'payment_id',
                'payment_reference',
                'order_id',
                'customer_id',
                'status'
            ]);

            // Find the payment record
            $payment = Payment::find($data['payment_id']);
            if (!$payment) {
                throw new Exception("Payment not found with ID: {$data['payment_id']}");
            }

            // Verify payment reference matches
            if ($payment->payment_reference !== $data['payment_reference']) {
                throw new Exception("Payment reference mismatch for payment ID: {$data['payment_id']}");
            }

            // Check if payment is already confirmed
            if ($payment->status === Payment::STATUS_COMPLETED) {
                DB::rollBack();
                
                Log::info("Payment already confirmed", [
                    'saga_id' => $this->getSagaId(),
                    'payment_id' => $payment->id,
                    'payment_reference' => $payment->payment_reference
                ]);

                return $this->successResponse([
                    'payment_id' => $payment->id,
                    'payment_reference' => $payment->payment_reference,
                    'status' => $payment->status,
                    'already_confirmed' => true,
                    'confirmed_at' => $payment->confirmed_at?->toISOString()
                ]);
            }

            // Determine if payment should be confirmed based on current status
            $shouldConfirm = $this->shouldConfirmPayment($payment->status, $payment->payment_method);
            if (!$shouldConfirm['confirm']) {
                throw new Exception("Payment cannot be confirmed in current status: {$payment->status}. Reason: {$shouldConfirm['reason']}");
            }

            // Update payment status to completed
            $payment->update([
                'status' => Payment::STATUS_COMPLETED,
                'confirmed_at' => now(),
                'updated_at' => now(),
                'metadata' => json_encode(array_merge(
                    json_decode($payment->metadata ?? '{}', true),
                    [
                        'confirmed_by_saga' => $this->getSagaId(),
                        'confirmed_at' => now()->toISOString(),
                        'confirmation_step' => 'saga_confirmation'
                    ]
                ))
            ]);

            // Fire payment completed event
            event(new PaymentCompleted($payment));

            // Log successful confirmation
            Log::info("Payment confirmed successfully", [
                'saga_id' => $this->getSagaId(),
                'payment_id' => $payment->id,
                'payment_reference' => $payment->payment_reference,
                'amount' => $payment->amount,
                'currency' => $payment->currency
            ]);

            DB::commit();

            return $this->successResponse([
                'payment_id' => $payment->id,
                'payment_reference' => $payment->payment_reference,
                'order_id' => $payment->order_id,
                'customer_id' => $payment->customer_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'payment_method' => $payment->payment_method,
                'status' => $payment->status,
                'gateway_transaction_id' => $payment->gateway_transaction_id,
                'confirmed_at' => $payment->confirmed_at->toISOString(),
                'net_amount' => $payment->net_amount,
                'gateway_fee' => $payment->gateway_fee
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            $this->logError($e);
            
            Log::error("ConfirmPaymentActivity failed", [
                'saga_id' => $this->getSagaId(),
                'payment_id' => $data['payment_id'] ?? null,
                'payment_reference' => $data['payment_reference'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse($e->getMessage(), [
                'payment_id' => $data['payment_id'] ?? null,
                'payment_reference' => $data['payment_reference'] ?? null,
                'confirmation_step' => 'payment_confirmation'
            ]);
        }
    }

    /**
     * Determine if payment should be confirmed based on current status
     */
    private function shouldConfirmPayment(string $currentStatus, string $paymentMethod): array
    {
        // Define which statuses can be confirmed
        $confirmableStatuses = [
            Payment::STATUS_PENDING,
            Payment::STATUS_PROCESSING,
            Payment::STATUS_AUTHORIZED
        ];

        if (!in_array($currentStatus, $confirmableStatuses)) {
            return [
                'confirm' => false,
                'reason' => "Status '{$currentStatus}' is not confirmable"
            ];
        }

        // Special handling for different payment methods
        switch ($paymentMethod) {
            case 'bank_transfer':
                // Bank transfers need to be pending or processing to be confirmed
                if (!in_array($currentStatus, [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING])) {
                    return [
                        'confirm' => false,
                        'reason' => "Bank transfers can only be confirmed from pending or processing status"
                    ];
                }
                break;
                
            case 'credit_card':
            case 'debit_card':
                // Card payments can be confirmed from authorized status (capture)
                if ($currentStatus === Payment::STATUS_AUTHORIZED) {
                    return [
                        'confirm' => true,
                        'reason' => "Capturing authorized card payment"
                    ];
                }
                break;
        }

        return [
            'confirm' => true,
            'reason' => "Payment status allows confirmation"
        ];
    }
}
