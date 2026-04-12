<?php

namespace App\Workflows\Activities;

/**
 * Reserve Inventory Activity
 * 
 * Handles inventory reservation through the inventory service via RPC.
 * This activity ensures items are available and reserved for the order.
 */
class ReserveInventoryActivity extends BaseRpcActivity
{
    /**
     * Execute inventory reservation
     *
     * @param array $orderData Order data including items to reserve
     * @return array Reservation result with reservation ID
     */
    public function __invoke(array $orderData): array
    {
        $this->validateData($orderData, [
            'order_id',
            'items'
        ]);
        
        $reservationData = [
            'order_id' => $orderData['order_id'],
            'items' => $orderData['items'],
            'customer_id' => $orderData['customer_id'] ?? null,
            'reservation_type' => 'order',
            'expires_at' => now()->addHours(24)->toISOString() // 24-hour reservation
        ];
        
        $result = $this->callRpc('inventory-service', 'reserveItems', $reservationData);
        
        if (!$result['success']) {
            throw new \Exception("Inventory reservation failed: " . ($result['error'] ?? 'Unknown error'));
        }
        
        return $this->successResponse([
            'reservation_id' => $result['data']['reservation_id'],
            'reserved_items' => $result['data']['reserved_items'],
            'expires_at' => $result['data']['expires_at'],
            'total_reserved' => count($result['data']['reserved_items'])
        ]);
    }
}

