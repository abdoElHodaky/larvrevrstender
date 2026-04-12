<?php

namespace App\States;

class Paid extends OrderState
{
    public static $name = 'paid';

    public function color(): string
    {
        return 'emerald';
    }

    public function label(): string
    {
        return 'Paid';
    }

    public function description(): string
    {
        return 'Payment has been completed and order is ready for processing';
    }

    public function getNextStates(): array
    {
        return [Processing::class, Cancelled::class];
    }
}
