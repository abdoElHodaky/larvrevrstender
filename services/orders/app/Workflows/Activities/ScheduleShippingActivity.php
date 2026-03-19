<?php

namespace App\Workflows\Activities;

/**
 * Schedule Shipping Activity
 * 
 * Handles shipping scheduling through the shipping service via RPC.
 * This activity coordinates delivery logistics for the order.
 */
class ScheduleShippingActivity extends BaseRpcActivity
{
    /**
     * Execute shipping scheduling
     *
     * @param array $orderData Order data including shipping information
     * @return array Shipping result with shipment ID
     */
    public function __invoke(array $orderData): array
    {
        $this->validateData($orderData, [
            'order_id',
            'shipping_address',
            'items'
        ]);
        
        $shippingData = [
            'order_id' => $orderData['order_id'],
            'shipping_address' => $orderData['shipping_address'],
            'items' => $orderData['items'],
            'shipping_method' => $orderData['shipping_method'] ?? 'standard',
            'customer_id' => $orderData['customer_id'] ?? null,
            'special_instructions' => $orderData['special_instructions'] ?? null,
            'priority' => $orderData['priority'] ?? 'normal'
        ];
        
        $result = $this->callRpc('shipping-service', 'scheduleShipment', $shippingData);
        
        if (!$result['success']) {
            throw new \Exception("Shipping scheduling failed: " . ($result['error'] ?? 'Unknown error'));
        }
        
        return $this->successResponse([
            'shipment_id' => $result['data']['shipment_id'],
            'tracking_number' => $result['data']['tracking_number'],
            'estimated_delivery' => $result['data']['estimated_delivery'],
            'shipping_method' => $result['data']['shipping_method'],
            'carrier' => $result['data']['carrier']
        ]);
    }
}

