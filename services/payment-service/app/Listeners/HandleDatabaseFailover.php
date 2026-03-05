<?php

namespace App\Listeners;

use Shared\Listeners\PaymentServiceDatabaseFailoverHandler;

/**
 * Payment Service Database Failover Handler
 * 
 * Uses shared library implementation for consistent failover behavior.
 * All failover logic is centralized in the shared library.
 */
class HandleDatabaseFailover extends PaymentServiceDatabaseFailoverHandler
{
    // All implementation inherited from shared library
    // Service-specific configuration and business logic handled in shared handler
}

