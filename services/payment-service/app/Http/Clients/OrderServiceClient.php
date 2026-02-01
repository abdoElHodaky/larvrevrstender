<?php

namespace App\Http\Clients;

class OrderServiceClient extends BaseServiceClient
{
    public function __construct()
    {
        parent::__construct(config('services.order_service.url'));
    }

    /**
     * Get order details for payment processing.
     */
    public function getOrderDetails(int $orderId): ?array
    {
        try {
            $response = $this->get("/orders/{$orderId}");

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Update order payment status.
     */
    public function updateOrderPaymentStatus(int $orderId, string $status, array $paymentData = []): bool
    {
        try {
            $response = $this->put("/orders/{$orderId}/payment-status", [
                'status' => $status,
                'payment_data' => $paymentData,
                'updated_by' => 'payment_service',
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Confirm order payment completion.
     */
    public function confirmOrderPayment(int $orderId, string $transactionId, float $amount): bool
    {
        try {
            $response = $this->post("/orders/{$orderId}/confirm-payment", [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'confirmed_at' => now()->toISOString(),
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Handle payment failure for order.
     */
    public function handlePaymentFailure(int $orderId, string $reason, array $errorData = []): bool
    {
        try {
            $response = $this->post("/orders/{$orderId}/payment-failed", [
                'reason' => $reason,
                'error_data' => $errorData,
                'failed_at' => now()->toISOString(),
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get order payment history.
     */
    public function getOrderPaymentHistory(int $orderId): array
    {
        try {
            $response = $this->get("/orders/{$orderId}/payment-history");

            return $response->successful() ? $response->json('payment_history', []) : [];
        } catch (\Exception $e) {
            return [];
        }
    }
}
