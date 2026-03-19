<?php

namespace App\States;

class Accepted extends OrderState
{
    public static $name = 'accepted';

    public function color(): string
    {
        return 'green';
    }

    public function label(): string
    {
        return 'Accepted';
    }

    public function description(): string
    {
        return 'Order has been accepted and is ready for payment';
    }

    public function getNextStates(): array
    {
        return [AwaitingPayment::class, Cancelled::class];
    }
}
