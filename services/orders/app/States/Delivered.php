<?php

namespace App\States;

class Delivered extends OrderState
{
    public static $name = 'delivered';

    public function color(): string
    {
        return 'teal';
    }

    public function label(): string
    {
        return 'Delivered';
    }

    public function description(): string
    {
        return 'Order has been delivered to customer';
    }

    public function canBeCancelled(): bool
    {
        return false; // Cannot cancel once delivered
    }

    public function getNextStates(): array
    {
        return [Completed::class];
    }
}
