<?php

namespace Shared\Http\Clients;

class PaymentServiceClient extends BaseServiceClient
{
    public function processPayment(array $paymentData): ?array
    {
        $response = $this->post('/payments', $paymentData);
        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }

    public function getPaymentStatus(string $paymentId): ?array
    {
        $response = $this->get("/payments/{$paymentId}");
        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }

    public function refundPayment(string $paymentId, float $amount = null): ?array
    {
        $response = $this->post("/payments/{$paymentId}/refund", ['amount' => $amount]);
        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }
}
