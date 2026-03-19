<?php

namespace App\Listeners;

/**
 * Payment Service Database Failover Handler
 * 
 * Uses service-local implementation that extends shared base classes.
 * Service-specific configuration and business logic handled locally.
 */
class HandleDatabaseFailover extends PaymentServiceDatabaseFailoverHandler
{
    // All implementation inherited from service-specific handler
    // Base patterns inherited from shared library via PaymentServiceDatabaseFailoverHandler
}
