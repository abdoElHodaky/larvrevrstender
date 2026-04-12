<?php

namespace App\States\Bid;

/**
 * Pending Bid State
 * 
 * Initial state when bid is created but saga hasn't completed yet.
 * Bid is not yet active and can be cancelled if saga fails.
 */
class PendingState extends BidState
{
    public static string $name = 'pending';
    
    public function canTransitionTo(string $state): bool
    {
        return in_array($state, [
            ActiveState::class,
            CancelledState::class,
        ]);
    }
}

