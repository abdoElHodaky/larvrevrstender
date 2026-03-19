<?php

namespace App\States;

class Draft extends OrderState
{
    public static $name = 'draft';

    public function color(): string
    {
        return 'gray';
    }

    public function label(): string
    {
        return 'Draft';
    }

    public function description(): string
    {
        return 'Order is in draft state and can be modified';
    }

    public function getNextStates(): array
    {
        return [Submitted::class, Cancelled::class];
    }
}
