<?php

namespace App\States;

class AwaitingPayment extends OrderState
{
    public static $name = 'awaiting_payment';

    public function color(): string
    {
        return 'yellow';
    }

    public function label(): string
    {
        return 'Awaiting Payment';
    }

    public function description(): string
    {
        return 'Order is awaiting payment from customer';
    }

    public function requiresPayment(): bool
    {
        return true;
    }

    public function getNextStates(): array
    {
        return [Paid::class, Cancelled::class];
    }
}
