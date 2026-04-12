<?php

namespace App\Workflows\Compensation;

use App\Workflows\Activities\BaseRpcActivity;

/**
 * Release Inventory Compensation Activity
 * 
 * Handles inventory release as compensation when order processing fails.
 * This activity returns reserved items back to available inventory.
 */
class ReleaseInventoryActivity extends BaseRpcActivity
{
    /**
     * Execute inventory release
     *
     * @param string $reservationId Reservation ID to release
     * @return array Release result
     */
    public function __invoke(string $reservationId): array
    {
        if (empty($reservationId)) {
            throw new \Exception("Reservation ID is required for inventory release");
        }
        
        $releaseData = [
            'reservation_id' => $reservationId,
            'reason' => 'Order processing failed - automatic compensation',
            'release_type' => 'full',
            'saga_id' => $this->getSagaId()
        ];
        
        $result = $this->callRpc('inventory-service', 'releaseReservation', $releaseData);
        
        if (!$result['success']) {
            throw new \Exception("Inventory release failed: " . ($result['error'] ?? 'Unknown error'));
        }
        
        return $this->successResponse([
            'release_id' => $result['data']['release_id'],
            'released_items' => $result['data']['released_items'],
            'release_status' => $result['data']['status'],
            'original_reservation_id' => $reservationId
        ]);
    }
}

