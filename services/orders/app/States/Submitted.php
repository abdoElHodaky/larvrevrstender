<?php

namespace App\States;

class Submitted extends OrderState
{
    public static $name = 'submitted';

    public function color(): string
    {
        return 'blue';
    }

    public function label(): string
    {
        return 'Submitted';
    }

    public function description(): string
    {
        return 'Order has been submitted and is awaiting review';
    }

    public function getNextStates(): array
    {
        return [Accepted::class, Cancelled::class];
    }
}
