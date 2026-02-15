<?php

namespace App\States\Bid;

/**
 * Winning Bid State
 * 
 * Bid has won the auction and is awaiting completion.
 * This state is reached when auction ends with this as the highest bid.
 */
class WinningState extends BidState
{
    public static string $name = 'winning';
    
    public function canTransitionTo(string $state): bool
    {
        return in_array($state, [
            CompletedState::class,
            CancelledState::class,
        ]);
    }
}

