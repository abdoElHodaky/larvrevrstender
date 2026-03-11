<?php

namespace App\Workflows\Activities;

use App\Models\Payment;
use App\Events\PaymentInitiated;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Create Payment Record Activity
 * 
 * Creates a payment record in the database with atomic transaction.
 * This step persists the payment information after successful gateway processing.
 */
class CreatePaymentRecordActivity extends BaseRpcActivity
{
    /**
     * Execute the payment record creation activity
     *
     * @param array $data Payment data from gateway processing step
     * @return array Payment record creation result
     * @throws Exception
     */
    public function execute(array $data): array
    {
        Log::info("CreatePaymentRecordActivity started", [
            'saga_id' => $this->getSagaId(),
            'order_id' => $data['order_id'] ?? null,
            'payment_reference' => $data['payment_reference'] ?? null
        ]);

        DB::beginTransaction();
        try {
            // Validate required fields from previous step
            $this->validateData($data, [
                'payment_reference',
                'order_id',
                'amount',
                'currency',
                'payment_method',
                'customer_id',
                'gateway_transaction_id',
                'gateway_status'
            ]);

            // Check if payment record already exists (idempotency)
            $existingPayment = Payment::where('payment_reference', $data['payment_reference'])->first();
            if ($existingPayment) {
                DB::rollBack();
                
                Log::info("Payment record already exists", [
                    'saga_id' => $this->getSagaId(),
                    'payment_reference' => $data['payment_reference'],
                    'existing_payment_id' => $existingPayment->id
                ]);

                return $this->successResponse([
                    'payment_id' => $existingPayment->id,
                    'payment_reference' => $existingPayment->payment_reference,
                    'status' => $existingPayment->status,
                    'already_exists' => true,
                    'created_at' => $existingPayment->created_at->toISOString()
                ]);
            }

            // Determine payment status based on gateway response
            $paymentStatus = $this->determinePaymentStatus($data['gateway_status'], $data['payment_method']);

            // Create payment record
            $payment = Payment::create([
                'payment_reference' => $data['payment_reference'],
                'order_id' => $data['order_id'],
                'customer_id' => $data['customer_id'],
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'payment_method' => $data['payment_method'],
                'status' => $paymentStatus,
                'gateway_transaction_id' => $data['gateway_transaction_id'],
                'gateway_status' => $data['gateway_status'],
                'authorization_code' => $data['authorization_code'] ?? null,
                'gateway_response' => $data['gateway_response'] ?? null,
                'gateway_fee' => $data['gateway_fee'] ?? 0,
                'net_amount' => $data['net_amount'] ?? $data['amount'],
                'description' => $data['description'] ?? "Payment for order #{$data['order_id']}",
                'metadata' => json_encode([
                    'saga_id' => $this->getSagaId(),
                    'processed_at' => $data['processed_at'] ?? now()->toISOString(),
                    'gateway_data' => [
                        'transaction_id' => $data['gateway_transaction_id'],
                        'status' => $data['gateway_status'],
                        'response' => $data['gateway_response'] ?? null
                    ]
                ]),
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Fire payment initiated event
            event(new PaymentInitiated($payment));

            DB::commit();

            Log::info("CreatePaymentRecordActivity completed successfully", [
                'saga_id' => $this->getSagaId(),
                'payment_id' => $payment->id,
                'payment_reference' => $payment->payment_reference,
                'status' => $payment->status
            ]);

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
                'created_at' => $payment->created_at->toISOString()
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            $this->logError($e);
            
            Log::error("CreatePaymentRecordActivity failed", [
                'saga_id' => $this->getSagaId(),
                'order_id' => $data['order_id'] ?? null,
                'payment_reference' => $data['payment_reference'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse($e->getMessage(), [
                'order_id' => $data['order_id'] ?? null,
                'payment_reference' => $data['payment_reference'] ?? null,
                'creation_step' => 'database_record_creation'
            ]);
        }
    }

    /**
     * Determine payment status based on gateway response
     */
    private function determinePaymentStatus(string $gatewayStatus, string $paymentMethod): string
    {
        // Map gateway statuses to internal payment statuses
        $statusMapping = [
            // Credit card statuses
            'authorized' => Payment::STATUS_AUTHORIZED,
            'captured' => Payment::STATUS_COMPLETED,
            'succeeded' => Payment::STATUS_COMPLETED,
            'completed' => Payment::STATUS_COMPLETED,
            
            // Pending statuses (bank transfers, etc.)
            'pending' => Payment::STATUS_PENDING,
            'processing' => Payment::STATUS_PROCESSING,
            
            // Failed statuses
            'failed' => Payment::STATUS_FAILED,
            'declined' => Payment::STATUS_FAILED,
            'cancelled' => Payment::STATUS_CANCELLED,
            'voided' => Payment::STATUS_VOIDED,
            
            // Refunded statuses
            'refunded' => Payment::STATUS_REFUNDED,
            'partially_refunded' => Payment::STATUS_PARTIALLY_REFUNDED,
        ];

        $status = $statusMapping[$gatewayStatus] ?? Payment::STATUS_PENDING;

        // Special handling for different payment methods
        $statusOverride = match ($paymentMethod) {
            'bank_transfer' => $gatewayStatus === 'pending' ? Payment::STATUS_PENDING : null,
            'credit_card', 'debit_card' => $gatewayStatus === 'authorized' ? Payment::STATUS_AUTHORIZED : null,
            'paypal', 'stripe' => in_array($gatewayStatus, ['succeeded', 'completed']) ? Payment::STATUS_COMPLETED : null,
            default => null
        };

        if ($statusOverride !== null) {
            $status = $statusOverride;
        }

        return $status;
    }
}
