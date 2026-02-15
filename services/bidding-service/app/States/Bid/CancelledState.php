<?php

namespace App\States\Bid;

/**
 * Cancelled Bid State
 * 
 * Bid has been cancelled either due to saga failure or manual cancellation.
 * This is a terminal state.
 */
class CancelledState extends BidState
{
    public static string $name = 'cancelled';
    
    public function canTransitionTo(string $state): bool
    {
        // Terminal state - no transitions allowed
        return false;
    }
}

