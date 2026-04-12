<?php

namespace Shared\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Shared Log Facade
 * 
 * Provides a convenient facade for the SharedLoggingService across all microservices.
 * This facade enables easy use statements and consistent logging API.
 * 
 * @method static void emergency(string $message, array $context = [])
 * @method static void alert(string $message, array $context = [])
 * @method static void critical(string $message, array $context = [])
 * @method static void error(string $message, array $context = [])
 * @method static void warning(string $message, array $context = [])
 * @method static void notice(string $message, array $context = [])
 * @method static void info(string $message, array $context = [])
 * @method static void debug(string $message, array $context = [])
 * @method static void databaseFailover(string $event, array $context = [])
 * @method static void healthCheck(string $connection, bool $healthy, array $context = [])
 * @method static void requestCorrelation(string $event, array $context = [])
 * @method static void logToChannel(string $channel, string $level, string $message, array $context = [])
 * @method static void performance(string $operation, float $duration, array $context = [])
 * @method static void exception(\Throwable $exception, array $context = [])
 * @method static \Shared\Services\SharedLoggingService addContext(array $context)
 * @method static \Shared\Services\SharedLoggingService setUser($user)
 * @method static \Shared\Services\SharedLoggingService withContext(array $context)
 * @method static \Shared\Services\SharedLoggingService clearContext()
 * @method static string getRequestId()
 * @method static string getServiceName()
 * @method static array getContext()
 * @method static array createFailoverLogEntry(string $event, array $data = [])
 * @method static void batchLog(array $entries)
 * 
 * @see \Shared\Services\SharedLoggingService
 */
class SharedLog extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'shared.logging';
    }
}
