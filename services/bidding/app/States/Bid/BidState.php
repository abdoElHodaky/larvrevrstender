<?php

namespace App\States\Bid;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Base Bid State
 * 
 * Manages the lifecycle of bids through various states during saga execution.
 */
abstract class BidState extends State
{
    /**
     * Configure bid state transitions
     */
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingState::class)
            ->allowTransition(PendingState::class, ActiveState::class)
            ->allowTransition(PendingState::class, CancelledState::class)
            ->allowTransition(ActiveState::class, CancelledState::class)
            ->allowTransition(ActiveState::class, WinningState::class)
            ->allowTransition(WinningState::class, CompletedState::class)
            ->allowTransition(WinningState::class, CancelledState::class);
    }
}

