<?php

namespace App\Listeners;

/**
 * User Service Database Failover Handler
 * 
 * Uses service-local implementation that extends shared base classes.
 * Service-specific configuration and business logic handled locally.
 */
class HandleDatabaseFailover extends UserServiceDatabaseFailoverHandler
{
    // All implementation inherited from service-specific handler
    // Base patterns inherited from shared library via UserServiceDatabaseFailoverHandler
}
