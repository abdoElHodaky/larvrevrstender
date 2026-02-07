<?php

namespace App\States;

class Processing extends OrderState
{
    public static $name = 'processing';

    public function color(): string
    {
        return 'orange';
    }

    public function label(): string
    {
        return 'Processing';
    }

    public function description(): string
    {
        return 'Order is being processed and prepared for shipment';
    }

    public function getNextStates(): array
    {
        return [Shipped::class, Cancelled::class];
    }
}
