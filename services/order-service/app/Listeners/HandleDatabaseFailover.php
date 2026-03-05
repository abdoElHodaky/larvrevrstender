<?php

namespace App\Listeners;

/**
 * Order Service Database Failover Handler
 * 
 * Uses service-local implementation that extends shared base classes.
 * Service-specific configuration and business logic handled locally.
 */
class HandleDatabaseFailover extends OrderServiceDatabaseFailoverHandler
{
    // All implementation inherited from service-specific handler
    // Base patterns inherited from shared library via OrderServiceDatabaseFailoverHandler
}
