<?php

namespace Shared\HealthCheck;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Database Health Checker
 * 
 * Performs comprehensive health checks on database connections
 * for the failover system. Monitors connection status, query performance,
 * and replication lag across all database tiers.
 */
class DatabaseHealthChecker
{
    private array $config;
    private array $healthHistory;

    public function __construct()
    {
        $this->config = config('database-failover.health_check', []);
        $this->healthHistory = [];
    }

    /**
     * Perform health check on a specific database connection.
     *
     * @param string $connectionName The database connection to check
     * @return ConnectionHealthStatus Health status result
     */
    public function checkConnection(string $connectionName): ConnectionHealthStatus
    {
        $startTime = microtime(true);
        $status = new ConnectionHealthStatus($connectionName);

        try {
            // Basic connectivity check
            $this->performConnectivityCheck($connectionName, $status);
            
            // Query performance check
            $this->performQueryPerformanceCheck($connectionName, $status);
            
            // Database-specific checks
            $this->performDatabaseSpecificChecks($connectionName, $status);
            
            // Calculate overall health
            $status->setOverallHealth($this->calculateOverallHealth($status));
            
        } catch (\Exception $e) {
            $status->setHealthy(false);
            $status->addError('health_check_exception', $e->getMessage());
            Log::error("Database health check failed for {$connectionName}: " . $e->getMessage());
        }

        $endTime = microtime(true);
        $status->setCheckDuration(($endTime - $startTime) * 1000); // Convert to milliseconds

        // Update health history
        $this->updateHealthHistory($connectionName, $status);

        return $status;
    }

    /**
     * Check health of all configured database connections.
     *
     * @return array Array of ConnectionHealthStatus objects
     */
    public function checkAllConnections(): array
    {
        $connections = config('database-failover.connections', []);
        $results = [];

        foreach ($connections as $priority => $connectionName) {
            $results[$connectionName] = $this->checkConnection($connectionName);
        }

        return $results;
    }

    /**
     * Get health status from cache if available and fresh.
     *
     * @param string $connectionName The connection to get cached status for
     * @return ConnectionHealthStatus|null Cached status or null if not available
     */
    public function getCachedHealthStatus(string $connectionName): ?ConnectionHealthStatus
    {
        $cacheKey = "db_health_status_{$connectionName}";
        return Cache::get($cacheKey);
    }

    /**
     * Cache health status for specified duration.
     *
     * @param string $connectionName The connection name
     * @param ConnectionHealthStatus $status The status to cache
     * @return void
     */
    public function cacheHealthStatus(string $connectionName, ConnectionHealthStatus $status): void
    {
        $cacheKey = "db_health_status_{$connectionName}";
        $cacheDuration = $this->config['interval'] ?? 30; // seconds
        
        Cache::put($cacheKey, $status, $cacheDuration);
    }

    /**
     * Get health history for a connection.
     *
     * @param string $connectionName The connection name
     * @param int $limit Maximum number of history entries to return
     * @return array Health history entries
     */
    public function getHealthHistory(string $connectionName, int $limit = 10): array
    {
        $cacheKey = "db_health_history_{$connectionName}";
        $history = Cache::get($cacheKey, []);
        
        return array_slice($history, -$limit);
    }

    /**
     * Perform basic connectivity check.
     *
     * @param string $connectionName The connection to check
     * @param ConnectionHealthStatus $status Status object to update
     * @return void
     */
    private function performConnectivityCheck(string $connectionName, ConnectionHealthStatus $status): void
    {
        try {
            $timeout = $this->config['timeout'] ?? 5;
            
            // Set connection timeout
            $originalTimeout = ini_get('default_socket_timeout');
            ini_set('default_socket_timeout', $timeout);

            // Attempt to get PDO connection
            $pdo = DB::connection($connectionName)->getPdo();
            
            if ($pdo) {
                $status->setConnectable(true);
                $status->addMetric('connection_status', 'connected');
            } else {
                $status->setConnectable(false);
                $status->addError('connectivity', 'Failed to establish PDO connection');
            }

            // Restore original timeout
            ini_set('default_socket_timeout', $originalTimeout);

        } catch (\Exception $e) {
            $status->setConnectable(false);
            $status->addError('connectivity', $e->getMessage());
        }
    }

    /**
     * Perform query performance check.
     *
     * @param string $connectionName The connection to check
     * @param ConnectionHealthStatus $status Status object to update
     * @return void
     */
    private function performQueryPerformanceCheck(string $connectionName, ConnectionHealthStatus $status): void
    {
        if (!$status->isConnectable()) {
            return; // Skip if not connectable
        }

        try {
            $startTime = microtime(true);
            
            // Perform appropriate query based on database type
            if ($this->isPostgreSQLConnection($connectionName)) {
                $result = DB::connection($connectionName)->select('SELECT 1 as health_check, NOW() as current_time');
            } elseif ($this->isMySQLConnection($connectionName)) {
                $result = DB::connection($connectionName)->select('SELECT 1 as health_check, NOW() as current_time');
            } elseif ($this->isMongoDBConnection($connectionName)) {
                // For MongoDB, we'll use a different approach
                $result = $this->performMongoHealthQuery($connectionName);
            } else {
                // Generic SQL query
                $result = DB::connection($connectionName)->select('SELECT 1 as health_check');
            }

            $endTime = microtime(true);
            $queryTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

            $status->setQueryable(true);
            $status->addMetric('query_response_time', $queryTime);
            $status->addMetric('query_result_count', count($result));

            // Check if query time is within acceptable limits
            $maxQueryTime = $this->config['max_query_time'] ?? 1000; // 1 second default
            if ($queryTime > $maxQueryTime) {
                $status->addWarning('performance', "Query time ({$queryTime}ms) exceeds threshold ({$maxQueryTime}ms)");
            }

        } catch (\Exception $e) {
            $status->setQueryable(false);
            $status->addError('query_performance', $e->getMessage());
        }
    }

    /**
     * Perform database-specific health checks.
     *
     * @param string $connectionName The connection to check
     * @param ConnectionHealthStatus $status Status object to update
     * @return void
     */
    private function performDatabaseSpecificChecks(string $connectionName, ConnectionHealthStatus $status): void
    {
        if (!$status->isQueryable()) {
            return; // Skip if not queryable
        }

        try {
            if ($this->isPostgreSQLConnection($connectionName)) {
                $this->performPostgreSQLChecks($connectionName, $status);
            } elseif ($this->isMySQLConnection($connectionName)) {
                $this->performMySQLChecks($connectionName, $status);
            } elseif ($this->isMongoDBConnection($connectionName)) {
                $this->performMongoDBChecks($connectionName, $status);
            }
        } catch (\Exception $e) {
            $status->addWarning('database_specific', $e->getMessage());
        }
    }

    /**
     * Perform PostgreSQL-specific health checks.
     *
     * @param string $connectionName The connection to check
     * @param ConnectionHealthStatus $status Status object to update
     * @return void
     */
    private function performPostgreSQLChecks(string $connectionName, ConnectionHealthStatus $status): void
    {
        try {
            // Check database size
            $sizeQuery = "SELECT pg_size_pretty(pg_database_size(current_database())) as db_size";
            $sizeResult = DB::connection($connectionName)->select($sizeQuery);
            if (!empty($sizeResult)) {
                $status->addMetric('database_size', $sizeResult[0]->db_size);
            }

            // Check active connections
            $connectionsQuery = "SELECT count(*) as active_connections FROM pg_stat_activity WHERE state = 'active'";
            $connectionsResult = DB::connection($connectionName)->select($connectionsQuery);
            if (!empty($connectionsResult)) {
                $activeConnections = $connectionsResult[0]->active_connections;
                $status->addMetric('active_connections', $activeConnections);
                
                // Warn if too many active connections
                $maxConnections = $this->config['max_active_connections'] ?? 100;
                if ($activeConnections > $maxConnections) {
                    $status->addWarning('connections', "High number of active connections: {$activeConnections}");
                }
            }

            // Check for replication lag (if applicable)
            $this->checkPostgreSQLReplicationLag($connectionName, $status);

        } catch (\Exception $e) {
            $status->addWarning('postgresql_checks', $e->getMessage());
        }
    }

    /**
     * Perform MySQL-specific health checks.
     *
     * @param string $connectionName The connection to check
     * @param ConnectionHealthStatus $status Status object to update
     * @return void
     */
    private function performMySQLChecks(string $connectionName, ConnectionHealthStatus $status): void
    {
        try {
            // Check MySQL status variables
            $statusQuery = "SHOW STATUS LIKE 'Threads_connected'";
            $statusResult = DB::connection($connectionName)->select($statusQuery);
            if (!empty($statusResult)) {
                $status->addMetric('threads_connected', $statusResult[0]->Value);
            }

            // Check for replication status
            $this->checkMySQLReplicationStatus($connectionName, $status);

        } catch (\Exception $e) {
            $status->addWarning('mysql_checks', $e->getMessage());
        }
    }

    /**
     * Perform MongoDB-specific health checks.
     *
     * @param string $connectionName The connection to check
     * @param ConnectionHealthStatus $status Status object to update
     * @return void
     */
    private function performMongoDBChecks(string $connectionName, ConnectionHealthStatus $status): void
    {
        try {
            // Get MongoDB connection configuration
            $config = config("database.connections.{$connectionName}");
            
            if (!$config) {
                $status->addError('config', "MongoDB connection configuration not found for {$connectionName}");
                return;
            }

            // Check if MongoDB extension is available
            if (!extension_loaded('mongodb')) {
                $status->addError('extension', 'MongoDB PHP extension not loaded');
                return;
            }

            // Build MongoDB connection string
            $connectionString = $this->buildMongoConnectionString($config);
            
            // Create MongoDB client
            $client = new \MongoDB\Client($connectionString, [
                'connectTimeoutMS' => ($this->config['timeout'] ?? 5) * 1000,
                'serverSelectionTimeoutMS' => ($this->config['timeout'] ?? 5) * 1000,
            ]);

            // Perform basic ping test
            $startTime = microtime(true);
            $pingResult = $client->selectDatabase($config['database'])->command(['ping' => 1]);
            $pingTime = (microtime(true) - $startTime) * 1000;
            
            $status->addMetric('ping_time_ms', round($pingTime, 2));
            $status->addMetric('mongodb_status', 'connected');

            // Check server status
            $this->checkMongoDBServerStatus($client, $config['database'], $status);
            
            // Check replica set status if applicable
            $this->checkMongoDBReplicaSet($client, $status);
            
            // Check database statistics
            $this->checkMongoDBDatabaseStats($client, $config['database'], $status);

        } catch (\MongoDB\Driver\Exception\ConnectionTimeoutException $e) {
            $status->addError('connection_timeout', "MongoDB connection timeout: " . $e->getMessage());
        } catch (\MongoDB\Driver\Exception\ServerSelectionException $e) {
            $status->addError('server_selection', "MongoDB server selection failed: " . $e->getMessage());
        } catch (\MongoDB\Driver\Exception\AuthenticationException $e) {
            $status->addError('authentication', "MongoDB authentication failed: " . $e->getMessage());
        } catch (\Exception $e) {
            $status->addError('mongodb_checks', "MongoDB health check failed: " . $e->getMessage());
        }
    }

    /**
     * Build MongoDB connection string from configuration.
     *
     * @param array $config MongoDB connection configuration
     * @return string MongoDB connection string
     */
    private function buildMongoConnectionString(array $config): string
    {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 27017;
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;
        $database = $config['database'] ?? 'test';
        $authDatabase = $config['options']['database'] ?? 'admin';

        if ($username && $password) {
            return "mongodb://{$username}:{$password}@{$host}:{$port}/{$database}?authSource={$authDatabase}";
        }

        return "mongodb://{$host}:{$port}/{$database}";
    }

    /**
     * Check MongoDB server status.
     *
     * @param \MongoDB\Client $client MongoDB client
     * @param string $database Database name
     * @param ConnectionHealthStatus $status Status object to update
     * @return void
     */
    private function checkMongoDBServerStatus(\MongoDB\Client $client, string $database, ConnectionHealthStatus $status): void
    {
        try {
            $serverStatus = $client->selectDatabase($database)->command(['serverStatus' => 1]);
            $statusDoc = $serverStatus->toArray()[0];

            // Add server metrics
            if (isset($statusDoc->version)) {
                $status->addMetric('mongodb_version', $statusDoc->version);
            }

            if (isset($statusDoc->uptime)) {
                $status->addMetric('uptime_seconds', $statusDoc->uptime);
            }

            if (isset($statusDoc->connections)) {
                $connections = $statusDoc->connections;
                $status->addMetric('current_connections', $connections->current ?? 0);
                $status->addMetric('available_connections', $connections->available ?? 0);
                $status->addMetric('total_created_connections', $connections->totalCreated ?? 0);

                // Warn if connection usage is high
                $current = $connections->current ?? 0;
                $available = $connections->available ?? 0;
                $total = $current + $available;
                
                if ($total > 0) {
                    $usagePercent = ($current / $total) * 100;
                    $status->addMetric('connection_usage_percent', round($usagePercent, 2));
                    
                    if ($usagePercent > 80) {
                        $status->addWarning('high_connection_usage', "MongoDB connection usage is high: {$usagePercent}%");
                    }
                }
            }

            if (isset($statusDoc->mem)) {
                $mem = $statusDoc->mem;
                $status->addMetric('memory_resident_mb', $mem->resident ?? 0);
                $status->addMetric('memory_virtual_mb', $mem->virtual ?? 0);
            }

        } catch (\Exception $e) {
            $status->addWarning('server_status', "Could not get MongoDB server status: " . $e->getMessage());
        }
    }

    /**
     * Check MongoDB replica set status.
     *
     * @param \MongoDB\Client $client MongoDB client
     * @param ConnectionHealthStatus $status Status object to update
     * @return void
     */
    private function checkMongoDBReplicaSet(\MongoDB\Client $client, ConnectionHealthStatus $status): void
    {
        try {
            $replicaStatus = $client->selectDatabase('admin')->command(['replSetGetStatus' => 1]);
            $statusDoc = $replicaStatus->toArray()[0];

            if (isset($statusDoc->set)) {
                $status->addMetric('replica_set_name', $statusDoc->set);
                $status->addMetric('replica_set_state', $statusDoc->myState ?? 'unknown');

                // Check member states
                if (isset($statusDoc->members)) {
                    $primaryCount = 0;
                    $secondaryCount = 0;
                    $maxLag = 0;

                    foreach ($statusDoc->members as $member) {
                        if ($member->state === 1) { // PRIMARY
                            $primaryCount++;
                        } elseif ($member->state === 2) { // SECONDARY
                            $secondaryCount++;
                            
                            // Check replication lag
                            if (isset($member->optimeDate) && isset($statusDoc->date)) {
                                $lag = $statusDoc->date->toDateTime()->getTimestamp() - 
                                       $member->optimeDate->toDateTime()->getTimestamp();
                                $maxLag = max($maxLag, $lag);
                            }
                        }
                    }

                    $status->addMetric('replica_primary_count', $primaryCount);
                    $status->addMetric('replica_secondary_count', $secondaryCount);
                    $status->addMetric('replica_max_lag_seconds', $maxLag);

                    // Warn about replica set issues
                    if ($primaryCount === 0) {
                        $status->addError('no_primary', 'MongoDB replica set has no primary member');
                    } elseif ($primaryCount > 1) {
                        $status->addError('multiple_primaries', 'MongoDB replica set has multiple primary members');
                    }

                    $maxAllowedLag = $this->config['max_replication_lag'] ?? 30;
                    if ($maxLag > $maxAllowedLag) {
                        $status->addWarning('replication_lag', "MongoDB replication lag ({$maxLag}s) exceeds threshold ({$maxAllowedLag}s)");
                    }
                }
            }
        } catch (\MongoDB\Driver\Exception\CommandException $e) {
            // Not a replica set, which is fine
            $status->addMetric('replica_set_status', 'not_configured');
        } catch (\Exception $e) {
            $status->addWarning('replica_set_check', "Could not check MongoDB replica set status: " . $e->getMessage());
        }
    }

    /**
     * Check MongoDB database statistics.
     *
     * @param \MongoDB\Client $client MongoDB client
     * @param string $database Database name
     * @param ConnectionHealthStatus $status Status object to update
     * @return void
     */
    private function checkMongoDBDatabaseStats(\MongoDB\Client $client, string $database, ConnectionHealthStatus $status): void
    {
        try {
            $dbStats = $client->selectDatabase($database)->command(['dbStats' => 1]);
            $statsDoc = $dbStats->toArray()[0];

            if (isset($statsDoc->collections)) {
                $status->addMetric('collection_count', $statsDoc->collections);
            }

            if (isset($statsDoc->objects)) {
                $status->addMetric('document_count', $statsDoc->objects);
            }

            if (isset($statsDoc->dataSize)) {
                $status->addMetric('data_size_bytes', $statsDoc->dataSize);
                $status->addMetric('data_size_mb', round($statsDoc->dataSize / (1024 * 1024), 2));
            }

            if (isset($statsDoc->storageSize)) {
                $status->addMetric('storage_size_bytes', $statsDoc->storageSize);
                $status->addMetric('storage_size_mb', round($statsDoc->storageSize / (1024 * 1024), 2));
            }

            if (isset($statsDoc->indexes)) {
                $status->addMetric('index_count', $statsDoc->indexes);
            }

            if (isset($statsDoc->indexSize)) {
                $status->addMetric('index_size_bytes', $statsDoc->indexSize);
                $status->addMetric('index_size_mb', round($statsDoc->indexSize / (1024 * 1024), 2));
            }

        } catch (\Exception $e) {
            $status->addWarning('database_stats', "Could not get MongoDB database statistics: " . $e->getMessage());
        }
    }

    /**
     * Check PostgreSQL replication lag.
     *
     * @param string $connectionName The connection to check
     * @param ConnectionHealthStatus $status Status object to update
     * @return void
     */
    private function checkPostgreSQLReplicationLag(string $connectionName, ConnectionHealthStatus $status): void
    {
        try {
            // Check if this is a replica
            $replicaQuery = "SELECT pg_is_in_recovery() as is_replica";
            $replicaResult = DB::connection($connectionName)->select($replicaQuery);
            
            if (!empty($replicaResult) && $replicaResult[0]->is_replica) {
                // Get replication lag
                $lagQuery = "SELECT EXTRACT(EPOCH FROM (now() - pg_last_xact_replay_timestamp())) as lag_seconds";
                $lagResult = DB::connection($connectionName)->select($lagQuery);
                
                if (!empty($lagResult)) {
                    $lagSeconds = $lagResult[0]->lag_seconds;
                    $status->addMetric('replication_lag_seconds', $lagSeconds);
                    
                    // Warn if lag is too high
                    $maxLag = $this->config['max_replication_lag'] ?? 30; // 30 seconds default
                    if ($lagSeconds > $maxLag) {
                        $status->addWarning('replication_lag', "Replication lag ({$lagSeconds}s) exceeds threshold ({$maxLag}s)");
                    }
                }
            }
        } catch (\Exception $e) {
            // Replication lag check is optional, so just log the warning
            Log::debug("Could not check replication lag for {$connectionName}: " . $e->getMessage());
        }
    }

    /**
     * Check MySQL replication status.
     *
     * @param string $connectionName The connection to check
     * @param ConnectionHealthStatus $status Status object to update
     * @return void
     */
    private function checkMySQLReplicationStatus(string $connectionName, ConnectionHealthStatus $status): void
    {
        try {
            $slaveStatusQuery = "SHOW SLAVE STATUS";
            $slaveResult = DB::connection($connectionName)->select($slaveStatusQuery);
            
            if (!empty($slaveResult)) {
                $slaveStatus = $slaveResult[0];
                $status->addMetric('slave_io_running', $slaveStatus->Slave_IO_Running ?? 'Unknown');
                $status->addMetric('slave_sql_running', $slaveStatus->Slave_SQL_Running ?? 'Unknown');
                
                if (isset($slaveStatus->Seconds_Behind_Master)) {
                    $status->addMetric('seconds_behind_master', $slaveStatus->Seconds_Behind_Master);
                    
                    $maxLag = $this->config['max_replication_lag'] ?? 30;
                    if ($slaveStatus->Seconds_Behind_Master > $maxLag) {
                        $status->addWarning('replication_lag', "MySQL replication lag ({$slaveStatus->Seconds_Behind_Master}s) exceeds threshold");
                    }
                }
            }
        } catch (\Exception $e) {
            // Replication status check is optional
            Log::debug("Could not check MySQL replication status for {$connectionName}: " . $e->getMessage());
        }
    }

    /**
     * Perform MongoDB health query.
     *
     * @param string $connectionName The connection to check
     * @return array Query result
     */
    private function performMongoHealthQuery(string $connectionName): array
    {
        try {
            $config = config("database.connections.{$connectionName}");
            
            if (!$config || !extension_loaded('mongodb')) {
                return [['health_check' => 0, 'error' => 'MongoDB not available']];
            }

            $connectionString = $this->buildMongoConnectionString($config);
            $client = new \MongoDB\Client($connectionString, [
                'connectTimeoutMS' => 5000,
                'serverSelectionTimeoutMS' => 5000,
            ]);

            // Perform ping command
            $result = $client->selectDatabase($config['database'])->command(['ping' => 1]);
            $pingResult = $result->toArray()[0];

            return [['health_check' => $pingResult->ok ?? 0, 'ping' => 'success']];
            
        } catch (\Exception $e) {
            return [['health_check' => 0, 'error' => $e->getMessage()]];
        }
    }

    /**
     * Calculate overall health based on individual checks.
     *
     * @param ConnectionHealthStatus $status The status object
     * @return bool Overall health status
     */
    private function calculateOverallHealth(ConnectionHealthStatus $status): bool
    {
        // Connection must be both connectable and queryable
        if (!$status->isConnectable() || !$status->isQueryable()) {
            return false;
        }

        // Check if there are any critical errors
        $errors = $status->getErrors();
        $criticalErrors = ['connectivity', 'query_performance', 'health_check_exception'];
        
        foreach ($errors as $errorType => $errorMessage) {
            if (in_array($errorType, $criticalErrors)) {
                return false;
            }
        }

        // If we have warnings but no critical errors, still consider healthy
        return true;
    }

    /**
     * Update health history for a connection.
     *
     * @param string $connectionName The connection name
     * @param ConnectionHealthStatus $status The current status
     * @return void
     */
    private function updateHealthHistory(string $connectionName, ConnectionHealthStatus $status): void
    {
        $cacheKey = "db_health_history_{$connectionName}";
        $history = Cache::get($cacheKey, []);
        
        // Add current status to history
        $history[] = [
            'timestamp' => now()->toISOString(),
            'healthy' => $status->isHealthy(),
            'connectable' => $status->isConnectable(),
            'queryable' => $status->isQueryable(),
            'check_duration' => $status->getCheckDuration(),
            'error_count' => count($status->getErrors()),
            'warning_count' => count($status->getWarnings()),
        ];
        
        // Keep only last 50 entries
        $history = array_slice($history, -50);
        
        // Cache for 1 hour
        Cache::put($cacheKey, $history, 3600);
    }

    /**
     * Check if connection is PostgreSQL.
     *
     * @param string $connectionName The connection name
     * @return bool True if PostgreSQL connection
     */
    private function isPostgreSQLConnection(string $connectionName): bool
    {
        $driver = config("database.connections.{$connectionName}.driver");
        return in_array($driver, ['pgsql', 'postgresql']);
    }

    /**
     * Check if connection is MySQL.
     *
     * @param string $connectionName The connection name
     * @return bool True if MySQL connection
     */
    private function isMySQLConnection(string $connectionName): bool
    {
        $driver = config("database.connections.{$connectionName}.driver");
        return in_array($driver, ['mysql', 'mariadb']);
    }

    /**
     * Check if connection is MongoDB.
     *
     * @param string $connectionName The connection name
     * @return bool True if MongoDB connection
     */
    private function isMongoDBConnection(string $connectionName): bool
    {
        $driver = config("database.connections.{$connectionName}.driver");
        return in_array($driver, ['mongodb', 'mongo']);
    }
}
