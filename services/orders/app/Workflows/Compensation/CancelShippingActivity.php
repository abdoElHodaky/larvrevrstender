<?php

namespace App\Workflows\Compensation;

use App\Workflows\Activities\BaseRpcActivity;

/**
 * Cancel Shipping Compensation Activity
 * 
 * Handles shipping cancellation as compensation when order processing fails.
 * This activity cancels the scheduled shipment.
 */
class CancelShippingActivity extends BaseRpcActivity
{
    /**
     * Execute shipping cancellation
     *
     * @param string $shipmentId Shipment ID to cancel
     * @return array Cancellation result
     */
    public function __invoke(string $shipmentId): array
    {
        if (empty($shipmentId)) {
            throw new \Exception("Shipment ID is required for shipping cancellation");
        }
        
        $cancellationData = [
            'shipment_id' => $shipmentId,
            'reason' => 'Order processing failed - automatic compensation',
            'cancellation_type' => 'full',
            'saga_id' => $this->getSagaId()
        ];
        
        $result = $this->callRpc('shipping-service', 'cancelShipment', $cancellationData);
        
        if (!$result['success']) {
            throw new \Exception("Shipping cancellation failed: " . ($result['error'] ?? 'Unknown error'));
        }
        
        return $this->successResponse([
            'cancellation_id' => $result['data']['cancellation_id'],
            'cancellation_status' => $result['data']['status'],
            'refund_amount' => $result['data']['refund_amount'] ?? 0,
            'original_shipment_id' => $shipmentId
        ]);
    }
}

