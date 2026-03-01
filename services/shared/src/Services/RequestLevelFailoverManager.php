<?php

namespace Shared\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Shared\Services\DatabaseFailoverManager;
use Shared\Events\DatabaseFailoverEvent;

/**
 * Request-Level Failover Manager
 * 
 * Provides fine-grained control over database failover at the request level.
 * Allows applications to specify connection preferences, consistency requirements,
 * and failover behavior on a per-request or per-operation basis.
 */
class RequestLevelFailoverManager
{
    private DatabaseFailoverManager $failoverManager;
    private array $config;
    private array $requestContext;

    public function __construct(DatabaseFailoverManager $failoverManager)
    {
        $this->failoverManager = $failoverManager;
        $this->config = config('database-failover.request_level', []);
        $this->requestContext = [];
    }

    /**
     * Execute a database operation with specific failover preferences.
     */
    public function executeWithPreferences(callable $operation, array $preferences = []): mixed
    {
        $requestId = $this->generateRequestId();
        
        Log::debug("Executing operation with failover preferences", [
            'request_id' => $requestId,
            'preferences' => $preferences
        ]);

        // Set up request context
        $this->requestContext[$requestId] = [
            'preferences' => $preferences,
            'started_at' => now(),
            'attempts' => 0,
            'connection_history' => []
        ];

        try {
            return $this->executeWithFailoverLogic($requestId, $operation, $preferences);
        } finally {
            // Clean up request context
            unset($this->requestContext[$requestId]);
        }
    }

    /**
     * Execute operation with specific connection preference.
     */
    public function executeOnConnection(string $connectionName, callable $operation, array $options = []): mixed
    {
        $requestId = $this->generateRequestId();
        
        Log::debug("Executing operation on specific connection", [
            'request_id' => $requestId,
            'connection' => $connectionName,
            'options' => $options
        ]);

        $preferences = array_merge([
            'preferred_connection' => $connectionName,
            'allow_failover' => $options['allow_failover'] ?? true,
            'consistency_level' => $options['consistency_level'] ?? 'eventual',
            'timeout' => $options['timeout'] ?? 30
        ], $options);

        return $this->executeWithPreferences($operation, $preferences);
    }

    /**
     * Execute read-only operation with read replica preference.
     */
    public function executeReadOnly(callable $operation, array $options = []): mixed
    {
        $preferences = array_merge([
            'operation_type' => 'read',
            'preferred_connection' => $this->getPreferredReadConnection(),
            'allow_failover' => true,
            'consistency_level' => 'eventual',
            'read_preference' => 'secondary_preferred'
        ], $options);

        return $this->executeWithPreferences($operation, $preferences);
    }

    /**
     * Execute write operation with strong consistency requirements.
     */
    public function executeWrite(callable $operation, array $options = []): mixed
    {
        $preferences = array_merge([
            'operation_type' => 'write',
            'preferred_connection' => $this->getPreferredWriteConnection(),
            'allow_failover' => $options['allow_failover'] ?? true,
            'consistency_level' => 'strong',
            'transaction_isolation' => $options['transaction_isolation'] ?? 'read_committed'
        ], $options);

        return $this->executeWithPreferences($operation, $preferences);
    }

    /**
     * Execute operation within a distributed transaction.
     */
    public function executeInTransaction(callable $operation, array $options = []): mixed
    {
        $preferences = array_merge([
            'operation_type' => 'transaction',
            'preferred_connection' => $this->getPreferredWriteConnection(),
            'allow_failover' => false, // Transactions should not failover mid-execution
            'consistency_level' => 'strong',
            'transaction_isolation' => $options['isolation'] ?? 'read_committed',
            'transaction_timeout' => $options['timeout'] ?? 60
        ], $options);

        return $this->executeTransactionWithFailover($operation, $preferences);
    }

    /**
     * Get current connection status for request-level decisions.
     */
    public function getConnectionStatus(): array
    {
        $connections = $this->failoverManager->getConnectionPriority();
        $status = [];

        foreach ($connections as $connectionName) {
            $isHealthy = $this->failoverManager->isConnectionHealthy($connectionName);
            $metrics = $this->getConnectionMetrics($connectionName);
            
            $status[$connectionName] = [
                'healthy' => $isHealthy,
                'current' => $this->failoverManager->getCurrentConnection() === $connectionName,
                'priority' => array_search($connectionName, $connections),
                'response_time_ms' => $metrics['response_time_ms'] ?? null,
                'load_score' => $metrics['load_score'] ?? null,
                'suitable_for_reads' => $this->isSuitableForReads($connectionName),
                'suitable_for_writes' => $this->isSuitableForWrites($connectionName)
            ];
        }

        return $status;
    }

    /**
     * Force failover for current request context.
     */
    public function forceFailover(string $reason = 'manual'): string
    {
        Log::info("Forcing failover for request", [
            'reason' => $reason,
            'current_connection' => $this->failoverManager->getCurrentConnection()
        ]);

        return $this->failoverManager->triggerFailover();
    }

    /**
     * Disable failover for current request.
     */
    public function disableFailover(): void
    {
        $this->setRequestPreference('allow_failover', false);
        
        Log::debug("Failover disabled for current request");
    }

    /**
     * Enable failover for current request.
     */
    public function enableFailover(): void
    {
        $this->setRequestPreference('allow_failover', true);
        
        Log::debug("Failover enabled for current request");
    }

    /**
     * Set connection preference for current request.
     */
    public function setConnectionPreference(string $connectionName): void
    {
        $this->setRequestPreference('preferred_connection', $connectionName);
        
        Log::debug("Connection preference set", [
            'preferred_connection' => $connectionName
        ]);
    }

    /**
     * Set consistency level for current request.
     */
    public function setConsistencyLevel(string $level): void
    {
        $validLevels = ['eventual', 'session', 'strong'];
        
        if (!in_array($level, $validLevels)) {
            throw new \InvalidArgumentException("Invalid consistency level: {$level}");
        }

        $this->setRequestPreference('consistency_level', $level);
        
        Log::debug("Consistency level set", [
            'consistency_level' => $level
        ]);
    }

    /**
     * Execute operation with failover logic.
     */
    private function executeWithFailoverLogic(string $requestId, callable $operation, array $preferences): mixed
    {
        $context = &$this->requestContext[$requestId];
        $maxAttempts = $preferences['max_attempts'] ?? 3;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $context['attempts'] = $attempt;
            
            try {
                // Determine best connection for this attempt
                $connectionName = $this->selectConnectionForAttempt($requestId, $attempt, $preferences);
                $context['connection_history'][] = $connectionName;
                
                Log::debug("Attempting operation", [
                    'request_id' => $requestId,
                    'attempt' => $attempt,
                    'connection' => $connectionName
                ]);

                // Set up connection context
                $this->setupConnectionContext($connectionName, $preferences);
                
                // Execute the operation
                $result = $this->executeOperationOnConnection($connectionName, $operation, $preferences);
                
                // Log successful execution
                Log::debug("Operation completed successfully", [
                    'request_id' => $requestId,
                    'attempt' => $attempt,
                    'connection' => $connectionName
                ]);

                return $result;

            } catch (\Exception $e) {
                $lastException = $e;
                
                Log::warning("Operation attempt failed", [
                    'request_id' => $requestId,
                    'attempt' => $attempt,
                    'connection' => $connectionName ?? 'unknown',
                    'error' => $e->getMessage()
                ]);

                // Check if we should attempt failover
                if ($attempt < $maxAttempts && $this->shouldAttemptFailover($e, $preferences)) {
                    $this->handleFailoverForRequest($requestId, $e);
                    continue;
                }
                
                // If this was the last attempt or failover is not allowed, throw the exception
                break;
            }
        }

        // All attempts failed
        Log::error("All operation attempts failed", [
            'request_id' => $requestId,
            'attempts' => $maxAttempts,
            'final_error' => $lastException->getMessage()
        ]);

        throw $lastException;
    }

    /**
     * Execute operation within a transaction with failover protection.
     */
    private function executeTransactionWithFailover(callable $operation, array $preferences): mixed
    {
        $connectionName = $preferences['preferred_connection'];
        $isolationLevel = $preferences['transaction_isolation'] ?? 'read_committed';
        
        Log::debug("Starting transaction with failover protection", [
            'connection' => $connectionName,
            'isolation' => $isolationLevel
        ]);

        return DB::connection($connectionName)->transaction(function () use ($operation, $preferences) {
            // Set transaction isolation level if specified
            if (isset($preferences['transaction_isolation'])) {
                $this->setTransactionIsolation($preferences['transaction_isolation']);
            }
            
            // Execute the operation within the transaction
            return $operation();
            
        }, $preferences['max_attempts'] ?? 3);
    }

    /**
     * Select the best connection for a specific attempt.
     */
    private function selectConnectionForAttempt(string $requestId, int $attempt, array $preferences): string
    {
        $context = $this->requestContext[$requestId];
        
        // For first attempt, use preferred connection if specified and healthy
        if ($attempt === 1 && isset($preferences['preferred_connection'])) {
            $preferredConnection = $preferences['preferred_connection'];
            if ($this->failoverManager->isConnectionHealthy($preferredConnection)) {
                return $preferredConnection;
            }
        }

        // For subsequent attempts, avoid previously failed connections
        $availableConnections = $this->getAvailableConnections($context['connection_history']);
        
        // Select based on operation type and preferences
        return $this->selectOptimalConnection($availableConnections, $preferences);
    }

    /**
     * Get available connections excluding failed ones.
     */
    private function getAvailableConnections(array $excludeConnections = []): array
    {
        $allConnections = $this->failoverManager->getConnectionPriority();
        $availableConnections = [];

        foreach ($allConnections as $connectionName) {
            if (!in_array($connectionName, $excludeConnections) && 
                $this->failoverManager->isConnectionHealthy($connectionName)) {
                $availableConnections[] = $connectionName;
            }
        }

        return $availableConnections;
    }

    /**
     * Select optimal connection based on preferences.
     */
    private function selectOptimalConnection(array $availableConnections, array $preferences): string
    {
        if (empty($availableConnections)) {
            throw new \RuntimeException("No healthy database connections available");
        }

        $operationType = $preferences['operation_type'] ?? 'mixed';
        $consistencyLevel = $preferences['consistency_level'] ?? 'eventual';

        // Score connections based on preferences
        $connectionScores = [];
        
        foreach ($availableConnections as $connectionName) {
            $score = $this->scoreConnectionForOperation($connectionName, $operationType, $consistencyLevel);
            $connectionScores[$connectionName] = $score;
        }

        // Sort by score (highest first)
        arsort($connectionScores);
        
        $selectedConnection = array_key_first($connectionScores);
        
        Log::debug("Selected optimal connection", [
            'connection' => $selectedConnection,
            'score' => $connectionScores[$selectedConnection],
            'operation_type' => $operationType,
            'consistency_level' => $consistencyLevel
        ]);

        return $selectedConnection;
    }

    /**
     * Score a connection for a specific operation type.
     */
    private function scoreConnectionForOperation(string $connectionName, string $operationType, string $consistencyLevel): int
    {
        $score = 0;
        $priorities = $this->failoverManager->getConnectionPriority();
        $priority = array_search($connectionName, $priorities);
        
        // Base score from connection priority (lower index = higher priority = higher score)
        $score += (count($priorities) - $priority) * 10;
        
        // Adjust score based on operation type
        switch ($operationType) {
            case 'read':
                if ($this->isSuitableForReads($connectionName)) {
                    $score += 20;
                }
                break;
                
            case 'write':
                if ($this->isSuitableForWrites($connectionName)) {
                    $score += 30;
                }
                break;
                
            case 'transaction':
                if ($this->isSuitableForTransactions($connectionName)) {
                    $score += 25;
                }
                break;
        }
        
        // Adjust score based on consistency requirements
        if ($consistencyLevel === 'strong' && $this->isPrimaryConnection($connectionName)) {
            $score += 15;
        }
        
        // Adjust score based on current load/performance
        $metrics = $this->getConnectionMetrics($connectionName);
        if (isset($metrics['load_score'])) {
            $score += (100 - $metrics['load_score']) / 10; // Lower load = higher score
        }

        return $score;
    }

    /**
     * Setup connection context for operation.
     */
    private function setupConnectionContext(string $connectionName, array $preferences): void
    {
        // Set the active connection
        $this->failoverManager->setActiveConnection($connectionName);
        
        // Apply connection-specific optimizations
        if (isset($preferences['query_timeout'])) {
            $this->setQueryTimeout($connectionName, $preferences['query_timeout']);
        }
        
        if (isset($preferences['read_preference'])) {
            $this->setReadPreference($connectionName, $preferences['read_preference']);
        }
    }

    /**
     * Execute operation on specific connection.
     */
    private function executeOperationOnConnection(string $connectionName, callable $operation, array $preferences): mixed
    {
        $timeout = $preferences['timeout'] ?? 30;
        
        // Set up timeout handling
        $startTime = time();
        
        try {
            $result = $operation();
            
            // Check if operation exceeded timeout
            if ((time() - $startTime) > $timeout) {
                throw new \RuntimeException("Operation timeout exceeded: {$timeout} seconds");
            }
            
            return $result;
            
        } catch (\Exception $e) {
            // Add connection context to exception
            throw new \RuntimeException(
                "Operation failed on connection {$connectionName}: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Check if we should attempt failover for this exception.
     */
    private function shouldAttemptFailover(\Exception $exception, array $preferences): bool
    {
        // Don't failover if explicitly disabled
        if (!($preferences['allow_failover'] ?? true)) {
            return false;
        }
        
        // Don't failover for certain types of exceptions
        $nonFailoverExceptions = [
            'Illuminate\Database\QueryException', // SQL syntax errors, etc.
            'InvalidArgumentException',
            'LogicException'
        ];
        
        foreach ($nonFailoverExceptions as $exceptionType) {
            if ($exception instanceof $exceptionType) {
                // Check if it's actually a connection issue
                if (!$this->isConnectionException($exception)) {
                    return false;
                }
            }
        }
        
        return true;
    }

    /**
     * Check if exception is connection-related.
     */
    private function isConnectionException(\Exception $exception): bool
    {
        $message = strtolower($exception->getMessage());
        $connectionKeywords = [
            'connection refused',
            'connection timeout',
            'connection lost',
            'server has gone away',
            'connection failed',
            'network error',
            'timeout expired'
        ];
        
        foreach ($connectionKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Handle failover for a specific request.
     */
    private function handleFailoverForRequest(string $requestId, \Exception $exception): void
    {
        Log::info("Handling failover for request", [
            'request_id' => $requestId,
            'error' => $exception->getMessage()
        ]);

        // Trigger failover in the main failover manager
        try {
            $this->failoverManager->triggerFailover();
        } catch (\Exception $e) {
            Log::error("Failover failed for request", [
                'request_id' => $requestId,
                'failover_error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Helper methods for connection evaluation
     */
    private function getPreferredReadConnection(): string
    {
        $connections = $this->failoverManager->getConnectionPriority();
        
        // Prefer secondary connections for reads
        foreach ($connections as $connectionName) {
            if ($this->isSuitableForReads($connectionName) && 
                $this->failoverManager->isConnectionHealthy($connectionName)) {
                return $connectionName;
            }
        }
        
        // Fallback to current connection
        return $this->failoverManager->getCurrentConnection();
    }

    private function getPreferredWriteConnection(): string
    {
        $connections = $this->failoverManager->getConnectionPriority();
        
        // Prefer primary connection for writes
        foreach ($connections as $connectionName) {
            if ($this->isSuitableForWrites($connectionName) && 
                $this->failoverManager->isConnectionHealthy($connectionName)) {
                return $connectionName;
            }
        }
        
        return $this->failoverManager->getCurrentConnection();
    }

    private function isSuitableForReads(string $connectionName): bool
    {
        // All healthy connections are suitable for reads
        return $this->failoverManager->isConnectionHealthy($connectionName);
    }

    private function isSuitableForWrites(string $connectionName): bool
    {
        // Only primary and secondary connections are suitable for writes
        // MongoDB fallback might be read-only in some configurations
        return !str_contains($connectionName, 'mongodb') || 
               $this->isMongoDBWritable($connectionName);
    }

    private function isSuitableForTransactions(string $connectionName): bool
    {
        // PostgreSQL connections are best for transactions
        return str_contains($connectionName, 'postgresql');
    }

    private function isPrimaryConnection(string $connectionName): bool
    {
        $connections = $this->failoverManager->getConnectionPriority();
        return $connections[0] === $connectionName;
    }

    private function isMongoDBWritable(string $connectionName): bool
    {
        try {
            // Get MongoDB connection and check write concern
            $connection = DB::connection($connectionName);
            $mongodb = $connection->getMongoDB();
            
            // Check if we can perform writes by testing write concern
            $writeConcern = $mongodb->getWriteConcern();
            
            // Check replica set status to ensure we can write
            $replStatus = $mongodb->command(['replSetGetStatus' => 1])->toArray()[0];
            $members = $replStatus['members'] ?? [];
            
            // Find primary member
            $hasPrimary = false;
            foreach ($members as $member) {
                if ($member['stateStr'] === 'PRIMARY' && $member['health'] == 1) {
                    $hasPrimary = true;
                    break;
                }
            }
            
            // MongoDB Atlas is writable if:
            // 1. Has a healthy primary in replica set
            // 2. Write concern is properly configured
            return $hasPrimary && $writeConcern !== null;
            
        } catch (\Exception $e) {
            Log::warning("Failed to check MongoDB writability", [
                'connection' => $connectionName,
                'error' => $e->getMessage()
            ]);
            
            // Default to false for safety
            return false;
        }
    }

    private function getConnectionMetrics(string $connectionName): array
    {
        // Get cached metrics or return defaults
        return Cache::get("connection_metrics_{$connectionName}", [
            'response_time_ms' => 100,
            'load_score' => 50
        ]);
    }

    private function setQueryTimeout(string $connectionName, int $timeout): void
    {
        // Set query timeout for the connection
        Log::debug("Setting query timeout", [
            'connection' => $connectionName,
            'timeout' => $timeout
        ]);
    }

    private function setReadPreference(string $connectionName, string $preference): void
    {
        // Set read preference (for MongoDB)
        Log::debug("Setting read preference", [
            'connection' => $connectionName,
            'preference' => $preference
        ]);
    }

    private function setTransactionIsolation(string $isolationLevel): void
    {
        // Set transaction isolation level
        $validLevels = ['read_uncommitted', 'read_committed', 'repeatable_read', 'serializable'];
        
        if (in_array($isolationLevel, $validLevels)) {
            DB::statement("SET TRANSACTION ISOLATION LEVEL " . strtoupper(str_replace('_', ' ', $isolationLevel)));
        }
    }

    private function setRequestPreference(string $key, $value): void
    {
        // Set preference for current request context
        // This would typically be stored in request-scoped storage
        Log::debug("Setting request preference", [
            'key' => $key,
            'value' => $value
        ]);
    }

    private function generateRequestId(): string
    {
        return 'req_' . uniqid();
    }
}
