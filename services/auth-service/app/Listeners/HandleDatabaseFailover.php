<?php

namespace App\Listeners;

/**
 * Auth Service Database Failover Handler
 * 
 * Uses service-local implementation that extends shared base classes.
 * Service-specific configuration and business logic handled locally.
 */
class HandleDatabaseFailover extends AuthServiceDatabaseFailoverHandler
{
    // All implementation inherited from service-specific handler
    // Base patterns inherited from shared library via AuthServiceDatabaseFailoverHandler
}
