<?php

namespace Shared\Contracts;

use Shared\Events\DatabaseFailoverEvent;

/**
 * Alert Manager Interface
 * 
 * Defines the contract for alert management systems.
 */
interface AlertManagerInterface
{
    /**
     * Handle a database failover event and trigger appropriate alerts.
     */
    public function handleFailoverEvent(DatabaseFailoverEvent $event): void;

    /**
     * Get recent alert history.
     */
    public function getAlertHistory(int $hours = 24): array;

    /**
     * Test alert channels to ensure they're working correctly.
     */
    public function testAlertChannels(): array;
}
