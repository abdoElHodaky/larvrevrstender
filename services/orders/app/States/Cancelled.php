<?php

namespace App\States;

class Cancelled extends OrderState
{
    public static $name = 'cancelled';

    public function color(): string
    {
        return 'red';
    }

    public function label(): string
    {
        return 'Cancelled';
    }

    public function description(): string
    {
        return 'Order has been cancelled';
    }

    public function canBeCancelled(): bool
    {
        return false; // Already cancelled
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
