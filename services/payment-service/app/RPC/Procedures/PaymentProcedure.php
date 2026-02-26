<?php

namespace App\RPC\Procedures;

use Shared\Core\BaseProcedure;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Transaction;
use App\Services\PaymentService;
use App\Services\ReservationService;
use App\Services\InvoiceService;
use App\Services\EscrowService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

/**
 * RPC Procedures for Payment Operations
 * 
 * Handles all payment-related RPC calls from other services.
 */
class PaymentProcedure extends BaseProcedure
{
    protected PaymentService $paymentService;
    protected ReservationService $reservationService;
    protected InvoiceService $invoiceService;
    protected EscrowService $escrowService;
    
    public function __construct(
        PaymentService $paymentService, 
        ReservationService $reservationService,
        InvoiceService $invoiceService,
        EscrowService $escrowService
    ) {
        $this->paymentService = $paymentService;
        $this->reservationService = $reservationService;
        $this->invoiceService = $invoiceService;
        $this->escrowService = $escrowService;
    }
    
    /**
     * Reserve funds for a bid
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function reserveFunds(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'user_id' => 'required|integer|min:1',
                'amount' => 'required|numeric|min:0.01',
                'auction_id' => 'required|integer|min:1',
                'bid_id' => 'integer|min:1',
                'payment_method_id' => 'string|max:255',
                'currency' => 'string|size:3',
                'expires_at' => 'date',
                'metadata' => 'array',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $result = $this->reservationService->reserveFunds($params);
            
            if ($result['success']) {
                return $this->successResponse([
                    'reservation' => $result['reservation'],
                    'message' => 'Funds reserved successfully',
                ]);
            } else {
                return $this->errorResponse($result['message'], $result['errors'] ?? [], $result['code'] ?? 400);
            }
            
        } catch (Exception $e) {
            Log::error('PaymentProcedure::reserveFunds failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to reserve funds', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Release reserved funds
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function releaseFunds(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'reservation_id' => 'required|string|max:255',
                'reason' => 'string|max:255',
                'metadata' => 'array',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $reservation = Reservation::where('reservation_id', $params['reservation_id'])->first();
            if (!$reservation) {
                return $this->errorResponse('Reservation not found', ['reservation_id' => $params['reservation_id']], 404);
            }
            
            $result = $this->reservationService->releaseFunds($params['reservation_id'], $params['reason'] ?? null);
            
            if ($result['success']) {
                return $this->successResponse([
                    'reservation' => $result['reservation'],
                    'message' => 'Funds released successfully',
                ]);
            } else {
                return $this->errorResponse($result['message'], $result['errors'] ?? [], $result['code'] ?? 400);
            }
            
        } catch (Exception $e) {
            Log::error('PaymentProcedure::releaseFunds failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to release funds', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Capture reserved funds (convert to payment)
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function captureFunds(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'reservation_id' => 'required|string|max:255',
                'capture_data' => 'array',
                'capture_data.final_amount' => 'numeric|min:0.01',
                'capture_data.fees' => 'numeric|min:0',
                'capture_data.description' => 'string|max:255',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $result = $this->reservationService->captureFunds($params['reservation_id'], $params['capture_data'] ?? []);
            
            if ($result['success']) {
                return $this->successResponse([
                    'payment' => $result['payment'],
                    'message' => 'Funds captured successfully',
                ]);
            } else {
                return $this->errorResponse($result['message'], $result['errors'] ?? [], $result['code'] ?? 400);
            }
            
        } catch (Exception $e) {
            Log::error('PaymentProcedure::captureFunds failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to capture funds', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Process payment for auction winner
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function processPayment(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'user_id' => 'required|integer|min:1',
                'amount' => 'required|numeric|min:0.01',
                'auction_id' => 'required|integer|min:1',
                'payment_method_id' => 'required|string|max:255',
                'currency' => 'string|size:3',
                'description' => 'string|max:255',
                'metadata' => 'array',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $result = $this->paymentService->processPayment($params);
            
            if ($result['success']) {
                return $this->successResponse([
                    'payment' => $result['payment'],
                    'transaction' => $result['transaction'] ?? null,
                    'message' => 'Payment processed successfully',
                ]);
            } else {
                return $this->errorResponse($result['message'], $result['errors'] ?? [], $result['code'] ?? 400);
            }
            
        } catch (Exception $e) {
            Log::error('PaymentProcedure::processPayment failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to process payment', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Issue refund
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function issueRefund(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'payment_id' => 'required|string|max:255',
                'amount' => 'numeric|min:0.01',
                'reason' => 'required|string|max:255',
                'metadata' => 'array',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $result = $this->paymentService->issueRefund($params);
            
            if ($result['success']) {
                return $this->successResponse([
                    'refund' => $result['refund'],
                    'message' => 'Refund issued successfully',
                ]);
            } else {
                return $this->errorResponse($result['message'], $result['errors'] ?? [], $result['code'] ?? 400);
            }
            
        } catch (Exception $e) {
            Log::error('PaymentProcedure::issueRefund failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to issue refund', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get payment status
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function getStatus(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'payment_id' => 'required|string|max:255',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $payment = Payment::where('payment_id', $params['payment_id'])->first();
            if (!$payment) {
                return $this->errorResponse('Payment not found', ['payment_id' => $params['payment_id']], 404);
            }
            
            return $this->successResponse([
                'payment' => $payment->toArray(),
                'status' => $payment->status,
                'payment_id' => $params['payment_id'],
            ]);
            
        } catch (Exception $e) {
            Log::error('PaymentProcedure::getStatus failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to get payment status', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get reservation status
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function getReservationStatus(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'reservation_id' => 'required|string|max:255',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $reservation = Reservation::where('reservation_id', $params['reservation_id'])->first();
            if (!$reservation) {
                return $this->errorResponse('Reservation not found', ['reservation_id' => $params['reservation_id']], 404);
            }
            
            return $this->successResponse([
                'reservation' => $reservation->toArray(),
                'status' => $reservation->status,
                'reservation_id' => $params['reservation_id'],
            ]);
            
        } catch (Exception $e) {
            Log::error('PaymentProcedure::getReservationStatus failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to get reservation status', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get user's payment methods
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function getUserPaymentMethods(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'user_id' => 'required|integer|min:1',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $result = $this->paymentService->getUserPaymentMethods($params['user_id']);
            
            return $this->successResponse([
                'payment_methods' => $result['payment_methods'],
                'user_id' => $params['user_id'],
            ]);
            
        } catch (Exception $e) {
            Log::error('PaymentProcedure::getUserPaymentMethods failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to get payment methods', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Calculate fees for payment
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function calculateFees(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'amount' => 'required|numeric|min:0.01',
                'payment_type' => 'required|string|in:bid_deposit,final_payment,refund',
                'context' => 'array',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $result = $this->paymentService->calculateFees(
                $params['amount'],
                $params['payment_type'],
                $params['context'] ?? []
            );
            
            return $this->successResponse([
                'fees' => $result['fees'],
                'total_amount' => $result['total_amount'],
                'base_amount' => $params['amount'],
                'payment_type' => $params['payment_type'],
            ]);
            
        } catch (Exception $e) {
            Log::error('PaymentProcedure::calculateFees failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to calculate fees', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create invoice from order data
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function createInvoice(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'invoice_data' => 'required|array',
                'invoice_data.order_id' => 'required|integer|min:1',
                'invoice_data.customer_id' => 'required|integer|min:1',
                'invoice_data.merchant_id' => 'required|integer|min:1',
                'invoice_data.subtotal' => 'required|numeric|min:0',
                'invoice_data.currency' => 'string|size:3',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $invoice = $this->invoiceService->createInvoiceFromOrder($params['invoice_data']);
            
            return $this->successResponse([
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'order_id' => $invoice->order_id,
                'customer_id' => $invoice->customer_id,
                'merchant_id' => $invoice->merchant_id,
                'subtotal' => $invoice->subtotal,
                'total_amount' => $invoice->total_amount,
                'currency' => $invoice->currency,
                'status' => $invoice->status,
                'invoice_date' => $invoice->invoice_date,
                'due_date' => $invoice->due_date,
                'created_at' => $invoice->created_at,
            ]);
            
        } catch (Exception $e) {
            Log::error('PaymentProcedure::createInvoice failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to create invoice', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Send invoice to customer
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function sendInvoice(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'invoice_id' => 'required|integer|min:1',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $invoice = $this->invoiceService->sendInvoice($params['invoice_id']);
            
            return $this->successResponse([
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'sent_at' => $invoice->sent_at,
                'sent_to' => $invoice->sent_to,
            ]);
            
        } catch (Exception $e) {
            Log::error('PaymentProcedure::sendInvoice failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to send invoice', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get invoice by ID
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function getInvoice(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'invoice_id' => 'required|integer|min:1',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $invoice = $this->invoiceService->getInvoice($params['invoice_id']);
            
            return $this->successResponse([
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'order_id' => $invoice->order_id,
                'customer_id' => $invoice->customer_id,
                'merchant_id' => $invoice->merchant_id,
                'subtotal' => $invoice->subtotal,
                'total_amount' => $invoice->total_amount,
                'currency' => $invoice->currency,
                'status' => $invoice->status,
                'invoice_date' => $invoice->invoice_date,
                'due_date' => $invoice->due_date,
                'sent_at' => $invoice->sent_at,
                'paid_at' => $invoice->paid_at,
                'created_at' => $invoice->created_at,
                'updated_at' => $invoice->updated_at,
            ]);
            
        } catch (Exception $e) {
            Log::error('PaymentProcedure::getInvoice failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to get invoice', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create escrow account
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function createEscrow(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'escrow_data' => 'required|array',
                'escrow_data.order_id' => 'required|integer|min:1',
                'escrow_data.buyer_id' => 'required|integer|min:1',
                'escrow_data.seller_id' => 'required|integer|min:1',
                'escrow_data.amount' => 'required|numeric|min:0.01',
                'escrow_data.currency' => 'string|size:3',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $escrow = $this->escrowService->createEscrow($params['escrow_data']);
            
            return $this->successResponse([
                'id' => $escrow->id,
                'escrow_number' => $escrow->escrow_number,
                'order_id' => $escrow->order_id,
                'buyer_id' => $escrow->buyer_id,
                'seller_id' => $escrow->seller_id,
                'amount' => $escrow->amount,
                'currency' => $escrow->currency,
                'status' => $escrow->status,
                'created_at' => $escrow->created_at,
            ]);
            
        } catch (Exception $e) {
            Log::error('PaymentProcedure::createEscrow failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to create escrow', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Fund escrow account
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function fundEscrow(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'escrow_id' => 'required|integer|min:1',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $escrow = $this->escrowService->fundEscrow($params['escrow_id']);
            
            return $this->successResponse([
                'id' => $escrow->id,
                'escrow_number' => $escrow->escrow_number,
                'status' => $escrow->status,
                'funded_at' => $escrow->funded_at,
                'amount' => $escrow->amount,
                'currency' => $escrow->currency,
            ]);
            
        } catch (Exception $e) {
            Log::error('PaymentProcedure::fundEscrow failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to fund escrow', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Release escrow funds
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function releaseEscrow(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'escrow_id' => 'required|integer|min:1',
                'release_data' => 'array',
                'release_data.reason' => 'string',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $reason = $params['release_data']['reason'] ?? 'Order completed';
            $escrow = $this->escrowService->releaseEscrow($params['escrow_id'], $reason);
            
            return $this->successResponse([
                'id' => $escrow->id,
                'escrow_number' => $escrow->escrow_number,
                'status' => $escrow->status,
                'released_at' => $escrow->released_at,
                'release_reason' => $escrow->release_reason,
                'amount' => $escrow->amount,
                'currency' => $escrow->currency,
            ]);
            
        } catch (Exception $e) {
            Log::error('PaymentProcedure::releaseEscrow failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to release escrow', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get escrow by order ID
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function getEscrowByOrderId(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'order_id' => 'required|integer|min:1',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            // Find escrow by order_id (assuming there's a method for this)
            $escrow = $this->escrowService->getEscrowByOrderId($params['order_id']);
            
            if (!$escrow) {
                return $this->errorResponse('Escrow not found', ['order_id' => $params['order_id']], 404);
            }
            
            return $this->successResponse([
                'id' => $escrow->id,
                'escrow_number' => $escrow->escrow_number,
                'order_id' => $escrow->order_id,
                'buyer_id' => $escrow->buyer_id,
                'seller_id' => $escrow->seller_id,
                'amount' => $escrow->amount,
                'currency' => $escrow->currency,
                'status' => $escrow->status,
                'funded_at' => $escrow->funded_at,
                'released_at' => $escrow->released_at,
                'created_at' => $escrow->created_at,
                'updated_at' => $escrow->updated_at,
            ]);
            
        } catch (Exception $e) {
            Log::error('PaymentProcedure::getEscrowByOrderId failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to get escrow', ['error' => $e->getMessage()], 500);
        }
    }
}
