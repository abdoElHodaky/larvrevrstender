<?php

namespace Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Shared\Services\DatabaseFailoverManager;
use Shared\HealthCheck\DatabaseHealthChecker;

/**
 * Database Failover Middleware
 * 
 * Automatically handles database connection failures by triggering failover
 * to healthy connections. This middleware runs before each request to ensure
 * the application is using a healthy database connection.
 */
class DatabaseFailoverMiddleware
{
    private DatabaseFailoverManager $failoverManager;
    private DatabaseHealthChecker $healthChecker;
    private array $config;

    public function __construct(
        DatabaseFailoverManager $failoverManager,
        DatabaseHealthChecker $healthChecker
    ) {
        $this->failoverManager = $failoverManager;
        $this->healthChecker = $healthChecker;
        $this->config = config('database-failover', []);
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip failover middleware if disabled
        if (!$this->isFailoverEnabled()) {
            return $next($request);
        }

        try {
            // Ensure we have a healthy database connection before processing the request
            $this->ensureHealthyConnection($request);

            // Process the request
            $response = $next($request);

            // Check if any database errors occurred during request processing
            $this->handlePostRequestFailover($request, $response);

            return $response;

        } catch (\Exception $e) {
            // Handle database-related exceptions
            return $this->handleDatabaseException($request, $e, $next);
        }
    }

    /**
     * Ensure we have a healthy database connection before processing the request.
     *
     * @param Request $request
     * @return void
     */
    private function ensureHealthyConnection(Request $request): void
    {
        try {
            $healthyConnection = $this->failoverManager->getHealthyConnection();
            $currentConnection = Config::get('database.default');

            // If the healthy connection is different from current, switch
            if ($this->mapFailoverToLaravelConnection($healthyConnection) !== $currentConnection) {
                $this->switchToConnection($healthyConnection, $request);
            }

        } catch (\Exception $e) {
            Log::error("Failed to ensure healthy database connection: " . $e->getMessage());
            
            // If we can't get any healthy connection, we might need to handle graceful degradation
            $this->handleNoHealthyConnections($request, $e);
        }
    }

    /**
     * Handle database exceptions by attempting failover.
     *
     * @param Request $request
     * @param \Exception $exception
     * @param Closure $next
     * @return mixed
     */
    private function handleDatabaseException(Request $request, \Exception $exception, Closure $next)
    {
        // Check if this is a database-related exception
        if (!$this->isDatabaseException($exception)) {
            throw $exception; // Re-throw non-database exceptions
        }

        Log::warning("Database exception detected, attempting failover: " . $exception->getMessage());

        try {
            // Attempt failover
            $newConnection = $this->failoverManager->triggerFailover();
            $this->switchToConnection($newConnection, $request);

            Log::info("Failover successful, retrying request with connection: {$newConnection}");

            // Retry the request with the new connection
            return $next($request);

        } catch (\Exception $failoverException) {
            Log::error("Failover attempt failed: " . $failoverException->getMessage());

            // If failover fails, check if graceful degradation is possible
            return $this->handleFailoverFailure($request, $exception, $failoverException);
        }
    }

    /**
     * Handle post-request failover checks.
     *
     * @param Request $request
     * @param mixed $response
     * @return void
     */
    private function handlePostRequestFailover(Request $request, $response): void
    {
        // Check if the response indicates database issues
        if ($this->responseIndicatesDatabaseIssues($response)) {
            Log::warning("Response indicates potential database issues, checking connection health");
            
            try {
                $currentConnection = $this->failoverManager->getCurrentConnection();
                $healthStatus = $this->healthChecker->checkConnection($currentConnection);
                
                if (!$healthStatus->isHealthy()) {
                    Log::warning("Current connection is unhealthy, triggering failover");
                    $this->failoverManager->triggerFailover();
                }
            } catch (\Exception $e) {
                Log::error("Post-request health check failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Switch to a specific database connection.
     *
     * @param string $failoverConnectionName
     * @param Request $request
     * @return void
     */
    private function switchToConnection(string $failoverConnectionName, Request $request): void
    {
        $laravelConnectionName = $this->mapFailoverToLaravelConnection($failoverConnectionName);
        $previousConnection = Config::get('database.default');

        // Update the default database connection
        Config::set('database.default', $laravelConnectionName);

        // Log the connection switch
        Log::info("Database connection switched: {$previousConnection} -> {$laravelConnectionName}", [
            'failover_connection' => $failoverConnectionName,
            'laravel_connection' => $laravelConnectionName,
            'request_path' => $request->path(),
            'request_method' => $request->method(),
        ]);

        // Add connection info to request for debugging
        $request->attributes->set('database_connection', $laravelConnectionName);
        $request->attributes->set('failover_connection', $failoverConnectionName);
    }

    /**
     * Handle the case when no healthy connections are available.
     *
     * @param Request $request
     * @param \Exception $exception
     * @return void
     * @throws \Exception
     */
    private function handleNoHealthyConnections(Request $request, \Exception $exception): void
    {
        $serviceName = $this->getServiceName($request);
        
        // Check if graceful degradation is enabled for this service
        if ($this->failoverManager->isGracefulDegradationEnabled($serviceName)) {
            Log::warning("No healthy connections available, enabling graceful degradation for service: {$serviceName}");
            
            // Set a flag to indicate degraded mode
            $request->attributes->set('database_degraded_mode', true);
            
            // You could implement specific degradation logic here
            // For example, switch to read-only mode, use cached data, etc.
            
        } else {
            // If graceful degradation is not enabled, throw the exception
            Log::error("No healthy connections available and graceful degradation disabled for service: {$serviceName}");
            throw $exception;
        }
    }

    /**
     * Handle failover failure scenarios.
     *
     * @param Request $request
     * @param \Exception $originalException
     * @param \Exception $failoverException
     * @return mixed
     * @throws \Exception
     */
    private function handleFailoverFailure(Request $request, \Exception $originalException, \Exception $failoverException)
    {
        $serviceName = $this->getServiceName($request);
        
        // Check if this is a critical operation that cannot be degraded
        if ($this->isCriticalOperation($request, $serviceName)) {
            Log::error("Critical operation failed and failover unsuccessful", [
                'service' => $serviceName,
                'operation' => $this->getOperationName($request),
                'original_error' => $originalException->getMessage(),
                'failover_error' => $failoverException->getMessage(),
            ]);
            
            // For critical operations, throw the original exception
            throw $originalException;
        }

        // For non-critical operations, attempt graceful degradation
        if ($this->failoverManager->isGracefulDegradationEnabled($serviceName)) {
            Log::warning("Enabling graceful degradation after failover failure", [
                'service' => $serviceName,
                'operation' => $this->getOperationName($request),
            ]);
            
            $request->attributes->set('database_degraded_mode', true);
            $request->attributes->set('degradation_reason', 'failover_failure');
            
            // Return a degraded response or continue with limited functionality
            return $this->createDegradedResponse($request);
        }

        // If no graceful degradation is possible, throw the original exception
        throw $originalException;
    }

    /**
     * Check if failover is enabled.
     *
     * @return bool
     */
    private function isFailoverEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    /**
     * Check if an exception is database-related.
     *
     * @param \Exception $exception
     * @return bool
     */
    private function isDatabaseException(\Exception $exception): bool
    {
        $databaseExceptionTypes = [
            'Illuminate\Database\QueryException',
            'PDOException',
            'Doctrine\DBAL\Exception\ConnectionException',
            'Illuminate\Database\ConnectionException',
        ];

        foreach ($databaseExceptionTypes as $type) {
            if ($exception instanceof $type) {
                return true;
            }
        }

        // Check exception message for database-related keywords
        $message = strtolower($exception->getMessage());
        $databaseKeywords = [
            'connection refused',
            'connection timeout',
            'connection lost',
            'server has gone away',
            'connection failed',
            'database',
            'sql',
            'pdo',
        ];

        foreach ($databaseKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the response indicates database issues.
     *
     * @param mixed $response
     * @return bool
     */
    private function responseIndicatesDatabaseIssues($response): bool
    {
        // Check for HTTP status codes that might indicate database issues
        if (method_exists($response, 'getStatusCode')) {
            $statusCode = $response->getStatusCode();
            if (in_array($statusCode, [500, 502, 503, 504])) {
                return true;
            }
        }

        // Check response content for database error indicators
        if (method_exists($response, 'getContent')) {
            $content = strtolower($response->getContent());
            $errorIndicators = [
                'database error',
                'connection failed',
                'query failed',
                'sql error',
            ];

            foreach ($errorIndicators as $indicator) {
                if (strpos($content, $indicator) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the service name from the request.
     *
     * @param Request $request
     * @return string
     */
    private function getServiceName(Request $request): string
    {
        // Try to get service name from various sources
        
        // From request headers
        if ($request->hasHeader('X-Service-Name')) {
            return $request->header('X-Service-Name');
        }

        // From environment variable
        if ($serviceName = env('SERVICE_NAME')) {
            return $serviceName;
        }

        // From request path (assuming service name is in the path)
        $pathSegments = explode('/', trim($request->path(), '/'));
        if (!empty($pathSegments[0])) {
            return $pathSegments[0];
        }

        // Default fallback
        return 'unknown-service';
    }

    /**
     * Get the operation name from the request.
     *
     * @param Request $request
     * @return string
     */
    private function getOperationName(Request $request): string
    {
        // Combine method and path for operation identification
        return $request->method() . ' ' . $request->path();
    }

    /**
     * Check if the current operation is critical for the service.
     *
     * @param Request $request
     * @param string $serviceName
     * @return bool
     */
    private function isCriticalOperation(Request $request, string $serviceName): bool
    {
        $serviceConfig = $this->config['services'][$serviceName] ?? [];
        $criticalOperations = $serviceConfig['critical_operations'] ?? [];

        if (empty($criticalOperations)) {
            return false;
        }

        $operationName = $this->getOperationName($request);
        $path = $request->path();
        $method = $request->method();

        // Check if the current operation matches any critical operations
        foreach ($criticalOperations as $criticalOp) {
            if (strpos($operationName, $criticalOp) !== false ||
                strpos($path, $criticalOp) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a degraded response for non-critical operations.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    private function createDegradedResponse(Request $request)
    {
        // This is a placeholder - actual implementation would depend on the specific service
        // and what kind of degraded functionality is appropriate
        
        return response()->json([
            'status' => 'degraded',
            'message' => 'Service is operating in degraded mode due to database issues',
            'timestamp' => now()->toISOString(),
        ], 503); // Service Unavailable
    }

    /**
     * Map failover connection names to Laravel connection names.
     *
     * @param string $failoverConnectionName
     * @return string
     */
    private function mapFailoverToLaravelConnection(string $failoverConnectionName): string
    {
        $mapping = [
            'neon_postgresql' => 'pgsql',
            'cloud_postgresql' => 'pgsql_secondary',
            'mongodb_atlas' => 'mongodb',
        ];

        return $mapping[$failoverConnectionName] ?? $failoverConnectionName;
    }
}
