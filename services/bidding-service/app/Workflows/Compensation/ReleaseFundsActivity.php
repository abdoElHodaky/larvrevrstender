<?php

namespace App\Workflows\Compensation;

use App\Workflows\Activities\BaseRpcActivity;

/**
 * Release Funds Compensation Activity
 * 
 * Releases reserved funds when the bid placement saga fails.
 * This compensation activity ensures funds are not locked indefinitely.
 */
class ReleaseFundsActivity extends BaseRpcActivity
{
    /**
     * Execute fund release compensation
     *
     * @param array $compensationData Data from the failed saga step
     * @return array Fund release result
     */
    public function __invoke(array $compensationData): array
    {
        $this->validateData($compensationData, [
            'reservation_id',
            'user_id'
        ]);
        
        $releaseData = [
            'reservation_id' => $compensationData['reservation_id'],
            'user_id' => $compensationData['user_id'],
            'reason' => 'saga_compensation',
            'saga_id' => $this->getSagaId(),
            'description' => "Fund release due to failed bid placement saga",
        ];
        
        try {
            $result = $this->callRpc('payment-service', 'releaseFunds', $releaseData);
            
            if (!$result['success']) {
                // Log the error but don't throw - compensation should be idempotent
                $this->logError(new \Exception("Fund release failed: " . ($result['error'] ?? 'Unknown error')));
                
                return $this->errorResponse(
                    "Fund release failed but saga compensation continues",
                    ['original_error' => $result['error'] ?? 'Unknown error']
                );
            }
            
            return $this->successResponse([
                'reservation_id' => $compensationData['reservation_id'],
                'released_amount' => $result['data']['released_amount'],
                'user_id' => $compensationData['user_id'],
                'compensation_completed' => true,
            ]);
            
        } catch (\Exception $e) {
            // Log but don't re-throw - compensation should be resilient
            $this->logError($e);
            
            return $this->errorResponse(
                "Fund release compensation failed but saga continues",
                ['error' => $e->getMessage()]
            );
        }
    }
}

