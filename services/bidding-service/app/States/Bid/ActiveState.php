<?php

namespace App\States\Bid;

/**
 * Active Bid State
 * 
 * Bid is active and participating in the auction.
 * This state is reached after successful saga completion.
 */
class ActiveState extends BidState
{
    public static string $name = 'active';
    
    public function canTransitionTo(string $state): bool
    {
        return in_array($state, [
            CancelledState::class,
            WinningState::class,
        ]);
    }
}

