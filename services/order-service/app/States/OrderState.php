<?php

namespace App\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class OrderState extends State
{
    /**
     * Get the color for UI representation
     */
    abstract public function color(): string;

    /**
     * Get the human-readable label
     */
    abstract public function label(): string;

    /**
     * Get the description of this state
     */
    abstract public function description(): string;

    /**
     * Check if this state allows cancellation
     */
    public function canBeCancelled(): bool
    {
        return true;
    }

    /**
     * Check if this state requires payment
     */
    public function requiresPayment(): bool
    {
        return false;
    }

    /**
     * Check if this state is a final state
     */
    public function isFinal(): bool
    {
        return false;
    }

    /**
     * Get the next possible states
     */
    public function getNextStates(): array
    {
        return [];
    }

    /**
     * Configure the state machine
     */
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Submitted::class)
            ->allowTransition(Submitted::class, Accepted::class)
            ->allowTransition(Accepted::class, AwaitingPayment::class)
            ->allowTransition(AwaitingPayment::class, Paid::class)
            ->allowTransition(Paid::class, Processing::class)
            ->allowTransition(Processing::class, Shipped::class)
            ->allowTransition(Shipped::class, Delivered::class)
            ->allowTransition(Delivered::class, Completed::class)
            // Cancellation transitions
            ->allowTransition(Draft::class, Cancelled::class)
            ->allowTransition(Submitted::class, Cancelled::class)
            ->allowTransition(Accepted::class, Cancelled::class)
            ->allowTransition(AwaitingPayment::class, Cancelled::class)
            ->allowTransition(Paid::class, Cancelled::class)
            ->allowTransition(Processing::class, Cancelled::class);
    }
}
