<?php

namespace App\Services;

use App\Events\EscrowCreated;
use App\Events\EscrowFunded;
use App\Events\EscrowReleased;
use App\Models\Escrow;
use App\Models\EscrowTransaction;
use App\Models\EscrowReleaseCondition;
use Illuminate\Support\Facades\DB;
use Shared\Procedures\Micro\CircuitBreakerProcedure;
use Shared\Core\BaseService;
use App\Services\Contracts\EscrowServiceInterface;

class EscrowService extends BaseService implements EscrowServiceInterface
{
    use CircuitBreakerProcedure;

    public function __construct(
        private PaymentService $paymentService
    ) {}

    /**
     * Create escrow account for order.
     */
    public function createEscrow(array $data): Escrow
    {
        return DB::transaction(function () use ($data) {
            $escrow = Escrow::create([
                'order_id' => $data['order_id'],
                'payment_id' => $data['payment_id'],
                'buyer_id' => $data['buyer_id'],
                'seller_id' => $data['seller_id'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'SAR',
                'status' => Escrow::STATUS_CREATED,
                'hold_until' => $data['hold_until'] ?? now()->addDays(7)
            ]);

            // Create default release conditions
            $this->createDefaultReleaseConditions($escrow);

            event(new EscrowCreated($escrow));

            return $escrow;
        });
    }

    /**
     * Fund escrow account by holding funds.
     */
    public function fundEscrow(int $escrowId): Escrow
    {
        return DB::transaction(function () use ($escrowId) {
            $escrow = Escrow::findOrFail($escrowId);

            if (!$escrow->canBeFunded()) {
                throw new \Exception('Escrow cannot be funded in current status: ' . $escrow->status);
            }

            // Hold funds in payment gateway using existing payment service with circuit breaker
            $holdResult = $this->executeWithCircuitBreaker(function () use ($escrow) {
                return $this->paymentService->holdFunds(
                    $escrow->payment_id,
                    $escrow->amount,
                    'Escrow hold for order #' . $escrow->order_id
                );
            });

            if (!$holdResult['success']) {
                throw new \Exception('Failed to hold funds: ' . $holdResult['error']);
            }

            // Update escrow status
            $escrow->update(['status' => Escrow::STATUS_FUNDED]);

            // Record transaction
            EscrowTransaction::create([
                'escrow_id' => $escrow->id,
                'type' => EscrowTransaction::TYPE_HOLD,
                'amount' => $escrow->amount,
                'reason' => 'Funds held in escrow',
                'external_reference' => $holdResult['reference'],
                'metadata' => [
                    'hold_result' => $holdResult,
                    'payment_id' => $escrow->payment_id
                ]
            ]);

            event(new EscrowFunded($escrow));

            return $escrow;
        });
    }

    /**
     * Release escrow funds.
     */
    public function releaseEscrow(int $escrowId, string $reason = 'Order completed'): Escrow
    {
        return DB::transaction(function () use ($escrowId, $reason) {
            $escrow = Escrow::findOrFail($escrowId);

            if (!$escrow->canBeReleased()) {
                $unmetConditions = $escrow->releaseConditions()->unmet()->pluck('condition_type')->toArray();
                throw new \Exception('Escrow cannot be released - unmet conditions: ' . implode(', ', $unmetConditions));
            }

            // Release funds using existing payment service with circuit breaker
            $releaseResult = $this->executeWithCircuitBreaker(function () use ($escrow, $reason) {
                return $this->paymentService->releaseFunds(
                    $escrow->payment_id,
                    $escrow->amount,
                    $reason
                );
            });

            if (!$releaseResult['success']) {
                throw new \Exception('Failed to release funds: ' . $releaseResult['error']);
            }

            // Update escrow status
            $escrow->update([
                'status' => Escrow::STATUS_RELEASED,
                'released_at' => now()
            ]);

            // Record transaction
            EscrowTransaction::create([
                'escrow_id' => $escrow->id,
                'type' => EscrowTransaction::TYPE_RELEASE,
                'amount' => $escrow->amount,
                'reason' => $reason,
                'external_reference' => $releaseResult['reference'],
                'metadata' => [
                    'release_result' => $releaseResult,
                    'payment_id' => $escrow->payment_id
                ]
            ]);

            event(new EscrowReleased($escrow));

            return $escrow;
        });
    }

    /**
     * Cancel escrow and return funds.
     */
    public function cancelEscrow(int $escrowId, string $reason = 'Order cancelled'): Escrow
    {
        return DB::transaction(function () use ($escrowId, $reason) {
            $escrow = Escrow::findOrFail($escrowId);

            if (!$escrow->canBeCancelled()) {
                throw new \Exception('Escrow cannot be cancelled in current status: ' . $escrow->status);
            }

            // If escrow was funded, release the funds back to buyer
            if ($escrow->status === Escrow::STATUS_FUNDED) {
                $releaseResult = $this->executeWithCircuitBreaker(function () use ($escrow, $reason) {
                    return $this->paymentService->releaseFunds(
                        $escrow->payment_id,
                        $escrow->amount,
                        $reason
                    );
                });

                if (!$releaseResult['success']) {
                    throw new \Exception('Failed to release funds during cancellation: ' . $releaseResult['error']);
                }
            }

            // Update escrow status
            $escrow->update(['status' => Escrow::STATUS_CANCELLED]);

            // Record transaction
            EscrowTransaction::create([
                'escrow_id' => $escrow->id,
                'type' => EscrowTransaction::TYPE_CANCEL,
                'amount' => $escrow->amount,
                'reason' => $reason,
                'external_reference' => $releaseResult['reference'] ?? null,
                'metadata' => [
                    'cancellation_reason' => $reason,
                    'original_status' => $escrow->getOriginal('status')
                ]
            ]);

            return $escrow;
        });
    }

    /**
     * Get escrow by ID.
     */
    public function getEscrow(int $escrowId): Escrow
    {
        return Escrow::with(['payment', 'transactions', 'releaseConditions'])->findOrFail($escrowId);
    }

    /**
     * Get escrows for a buyer.
     */
    public function getBuyerEscrows(int $buyerId, array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = Escrow::with(['payment', 'transactions', 'releaseConditions'])
            ->forBuyer($buyerId);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['active_only']) && $filters['active_only']) {
            $query->active();
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get escrows for a seller.
     */
    public function getSellerEscrows(int $sellerId, array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = Escrow::with(['payment', 'transactions', 'releaseConditions'])
            ->forSeller($sellerId);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['active_only']) && $filters['active_only']) {
            $query->active();
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Mark release condition as met.
     */
    public function markConditionAsMet(int $escrowId, string $conditionType, array $metadata = []): bool
    {
        $condition = EscrowReleaseCondition::where('escrow_id', $escrowId)
            ->where('condition_type', $conditionType)
            ->where('is_met', false)
            ->first();

        if (!$condition) {
            return false;
        }

        $condition->update([
            'is_met' => true,
            'met_at' => now(),
            'condition_data' => array_merge($condition->condition_data ?? [], $metadata)
        ]);

        return true;
    }

    /**
     * Check and update time-based conditions.
     */
    public function checkTimeBasedConditions(): int
    {
        $updatedCount = 0;
        
        $timeConditions = EscrowReleaseCondition::with('escrow')
            ->timeElapsed()
            ->unmet()
            ->get();

        foreach ($timeConditions as $condition) {
            if ($condition->checkTimeCondition()) {
                $condition->markAsMet();
                $updatedCount++;
            }
        }

        return $updatedCount;
    }

    /**
     * Get expired escrows that need attention.
     */
    public function getExpiredEscrows(): \Illuminate\Database\Eloquent\Collection
    {
        return Escrow::with(['payment', 'transactions', 'releaseConditions'])
            ->active()
            ->expired()
            ->get();
    }

    /**
     * Get escrow by order ID.
     */
    public function getEscrowByOrderId(int $orderId): ?Escrow
    {
        return Escrow::with(['payment', 'transactions', 'releaseConditions'])
            ->where('order_id', $orderId)
            ->first();
    }

    /**
     * Create default release conditions for an escrow.
     */
    private function createDefaultReleaseConditions(Escrow $escrow): void
    {
        $conditions = [
            [
                'escrow_id' => $escrow->id,
                'condition_type' => EscrowReleaseCondition::TYPE_DELIVERY_CONFIRMED,
                'condition_data' => ['required' => true, 'description' => 'Buyer must confirm delivery']
            ],
            [
                'escrow_id' => $escrow->id,
                'condition_type' => EscrowReleaseCondition::TYPE_TIME_ELAPSED,
                'condition_data' => ['days' => 7, 'description' => 'Automatic release after 7 days']
            ]
        ];

        foreach ($conditions as $conditionData) {
            EscrowReleaseCondition::create($conditionData);
        }
    }
}
