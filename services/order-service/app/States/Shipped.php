<?php

namespace App\States;

class Shipped extends OrderState
{
    public static $name = 'shipped';

    public function color(): string
    {
        return 'purple';
    }

    public function label(): string
    {
        return 'Shipped';
    }

    public function description(): string
    {
        return 'Order has been shipped and is on its way to customer';
    }

    public function canBeCancelled(): bool
    {
        return false; // Cannot cancel once shipped
    }

    public function getNextStates(): array
    {
        return [Delivered::class];
    }
}
