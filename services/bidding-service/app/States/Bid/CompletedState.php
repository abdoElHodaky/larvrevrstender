<?php

namespace App\States\Bid;

/**
 * Completed Bid State
 * 
 * Winning bid has been completed and payment processed.
 * This is a terminal state for successful bids.
 */
class CompletedState extends BidState
{
    public static string $name = 'completed';
    
    public function canTransitionTo(string $state): bool
    {
        // Terminal state - no transitions allowed
        return false;
    }
}

