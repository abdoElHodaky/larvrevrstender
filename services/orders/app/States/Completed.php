<?php

namespace App\States;

class Completed extends OrderState
{
    public static $name = 'completed';

    public function color(): string
    {
        return 'green';
    }

    public function label(): string
    {
        return 'Completed';
    }

    public function description(): string
    {
        return 'Order has been completed successfully';
    }

    public function canBeCancelled(): bool
    {
        return false; // Cannot cancel completed orders
    }

    public function isFinal(): bool
    {
        return true; // This is a final state
    }

    public function getNextStates(): array
    {
        return []; // No further transitions
    }
}
