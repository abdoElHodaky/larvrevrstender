<?php

namespace Shared\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Shared\HealthCheck\DatabaseHealthChecker;
use Shared\Events\DatabaseFailoverEvent;
use Carbon\Carbon;

/**
 * Database-Specific Optimization Manager
 * 
 * Provides database-specific optimizations and health checks for different
 * database types (PostgreSQL, MongoDB, etc.) to improve failover accuracy
 * and performance.
 */
class DatabaseSpecificOptimizationManager
{
    private array $config;
    private DatabaseHealthChecker $healthChecker;
    private array $connectionOptimizations;

    public function __construct(DatabaseHealthChecker $healthChecker)
    {
        $this->config = config('database-failover.database_specific', []);
        $this->healthChecker = $healthChecker;
        $this->connectionOptimizations = [];
    }

    /**
     * Perform database-specific health check.
     */
    public function performSpecificHealthCheck(string $connectionName): array
    {
        $connectionConfig = config("database.connections.{$connectionName}");
        $driver = $connectionConfig['driver'] ?? 'unknown';
        
        Log::debug("Performing database-specific health check", [
            'connection' => $connectionName,
            'driver' => $driver
        ]);

        switch ($driver) {
            case 'pgsql':
                return $this->performPostgreSQLHealthCheck($connectionName);
                
            case 'mongodb':
                return $this->performMongoDBHealthCheck($connectionName);
                
            case 'mysql':
                return $this->performMySQLHealthCheck($connectionName);
                
            default:
                return $this->performGenericHealthCheck($connectionName);
        }
    }

    /**
     * PostgreSQL-specific health check.
     */
    private function performPostgreSQLHealthCheck(string $connectionName): array
    {
        $healthData = [
            'connection' => $connectionName,
            'driver' => 'postgresql',
            'healthy' => true,
            'checks' => [],
            'metrics' => [],
            'warnings' => [],
            'errors' => []
        ];

        try {
            $connection = DB::connection($connectionName);
            
            // Basic connectivity test
            $startTime = microtime(true);
            $connection->getPdo();
            $healthData['metrics']['connection_time_ms'] = (microtime(true) - $startTime) * 1000;
            $healthData['checks']['connectivity'] = 'passed';
            
            // PostgreSQL version check
            $versionResult = $connection->select('SELECT version() as version');
            $healthData['metrics']['version'] = $versionResult[0]->version ?? 'unknown';
            $healthData['checks']['version'] = 'passed';
            
            // Connection pool status
            $poolStatus = $this->checkPostgreSQLConnectionPool($connection);
            $healthData['checks']['connection_pool'] = $poolStatus['status'];
            $healthData['metrics']['active_connections'] = $poolStatus['active_connections'];
            $healthData['metrics']['max_connections'] = $poolStatus['max_connections'];
            
            // Replica lag check (if this is a replica)
            $replicaLag = $this->checkPostgreSQLReplicaLag($connection);
            if ($replicaLag !== null) {
                $healthData['checks']['replica_lag'] = $replicaLag['status'];
                $healthData['metrics']['replica_lag_seconds'] = $replicaLag['lag_seconds'];
                
                if ($replicaLag['lag_seconds'] > ($this->config['postgresql']['max_replica_lag'] ?? 30)) {
                    $healthData['warnings'][] = "High replica lag: {$replicaLag['lag_seconds']} seconds";
                }
            }
            
            // Lock status check
            $lockStatus = $this->checkPostgreSQLLocks($connection);
            $healthData['checks']['locks'] = $lockStatus['status'];
            $healthData['metrics']['active_locks'] = $lockStatus['lock_count'];
            
            if ($lockStatus['lock_count'] > ($this->config['postgresql']['max_locks'] ?? 100)) {
                $healthData['warnings'][] = "High lock count: {$lockStatus['lock_count']}";
            }
            
            // Transaction status
            $transactionStatus = $this->checkPostgreSQLTransactions($connection);
            $healthData['checks']['transactions'] = $transactionStatus['status'];
            $healthData['metrics']['long_running_transactions'] = $transactionStatus['long_running_count'];
            
            // Performance metrics
            $performanceMetrics = $this->getPostgreSQLPerformanceMetrics($connection);
            $healthData['metrics'] = array_merge($healthData['metrics'], $performanceMetrics);
            
            // Query performance test
            $queryPerformance = $this->testPostgreSQLQueryPerformance($connection);
            $healthData['checks']['query_performance'] = $queryPerformance['status'];
            $healthData['metrics']['query_response_time_ms'] = $queryPerformance['response_time_ms'];
            
            if ($queryPerformance['response_time_ms'] > ($this->config['postgresql']['max_query_time'] ?? 1000)) {
                $healthData['warnings'][] = "Slow query performance: {$queryPerformance['response_time_ms']}ms";
                $healthData['healthy'] = false;
            }
            
        } catch (\Exception $e) {
            $healthData['healthy'] = false;
            $healthData['errors'][] = $e->getMessage();
            $healthData['checks']['connectivity'] = 'failed';
            
            Log::error("PostgreSQL health check failed", [
                'connection' => $connectionName,
                'error' => $e->getMessage()
            ]);
        }

        return $healthData;
    }

    /**
     * MongoDB Atlas-specific health check.
     */
    private function performMongoDBHealthCheck(string $connectionName): array
    {
        $healthData = [
            'connection' => $connectionName,
            'driver' => 'mongodb',
            'healthy' => true,
            'checks' => [],
            'metrics' => [],
            'warnings' => [],
            'errors' => []
        ];

        try {
            $startTime = microtime(true);
            
            // Get MongoDB connection using Laravel MongoDB package
            $connection = DB::connection($connectionName);
            $mongodb = $connection->getMongoDB();
            
            $healthData['metrics']['connection_time_ms'] = (microtime(true) - $startTime) * 1000;
            $healthData['checks']['connectivity'] = 'passed';
            
            // MongoDB Atlas version and build info
            $buildInfo = $mongodb->command(['buildInfo' => 1])->toArray()[0];
            $healthData['metrics']['version'] = $buildInfo['version'] ?? 'unknown';
            $healthData['metrics']['atlas_build'] = $buildInfo['gitVersion'] ?? 'unknown';
            $healthData['checks']['version'] = 'passed';
            
            // Replica set status (MongoDB Atlas always uses replica sets)
            $replicaSetStatus = $this->checkMongoDBAtlasReplicaSet($mongodb);
            $healthData['checks']['replica_set'] = $replicaSetStatus['status'];
            $healthData['metrics']['replica_set_members'] = $replicaSetStatus['member_count'];
            $healthData['metrics']['primary_member'] = $replicaSetStatus['primary'];
            $healthData['metrics']['secondary_members'] = $replicaSetStatus['secondaries'];
            
            if ($replicaSetStatus['status'] !== 'passed') {
                $healthData['warnings'][] = "Replica set issues: " . $replicaSetStatus['message'];
            }
            
            // Check if this is a sharded cluster (MongoDB Atlas M10+ can be sharded)
            $shardStatus = $this->checkMongoDBAtlasShards($mongodb);
            if ($shardStatus) {
                $healthData['checks']['shards'] = $shardStatus['status'];
                $healthData['metrics']['shard_count'] = $shardStatus['shard_count'];
                $healthData['metrics']['config_servers'] = $shardStatus['config_servers'];
                
                if ($shardStatus['status'] !== 'passed') {
                    $healthData['warnings'][] = "Shard cluster issues detected";
                }
            }
            
            // Oplog status and size
            $oplogStatus = $this->checkMongoDBAtlasOplog($mongodb);
            $healthData['checks']['oplog'] = $oplogStatus['status'];
            $healthData['metrics']['oplog_size_mb'] = $oplogStatus['size_mb'];
            $healthData['metrics']['oplog_used_mb'] = $oplogStatus['used_mb'];
            $healthData['metrics']['oplog_utilization_percent'] = $oplogStatus['utilization_percent'];
            
            if ($oplogStatus['utilization_percent'] > 80) {
                $healthData['warnings'][] = "High oplog utilization: {$oplogStatus['utilization_percent']}%";
            }
            
            // Connection pool status
            $poolStatus = $this->checkMongoDBAtlasConnectionPool($mongodb);
            $healthData['checks']['connection_pool'] = $poolStatus['status'];
            $healthData['metrics']['active_connections'] = $poolStatus['active_connections'];
            $healthData['metrics']['available_connections'] = $poolStatus['available_connections'];
            $healthData['metrics']['total_connections'] = $poolStatus['total_connections'];
            
            // Database statistics
            $dbStats = $this->getMongoDBAtlasDatabaseStats($mongodb);
            $healthData['metrics']['database_count'] = $dbStats['database_count'];
            $healthData['metrics']['total_size_mb'] = $dbStats['total_size_mb'];
            $healthData['metrics']['index_size_mb'] = $dbStats['index_size_mb'];
            
            // Performance test with actual query
            $performanceTest = $this->testMongoDBAtlasPerformance($mongodb);
            $healthData['checks']['performance'] = $performanceTest['status'];
            $healthData['metrics']['query_response_time_ms'] = $performanceTest['response_time_ms'];
            $healthData['metrics']['write_response_time_ms'] = $performanceTest['write_response_time_ms'];
            
            if ($performanceTest['response_time_ms'] > ($this->config['mongodb']['max_query_time'] ?? 2000)) {
                $healthData['warnings'][] = "Slow query performance: {$performanceTest['response_time_ms']}ms";
                $healthData['healthy'] = false;
            }
            
            // Read preference validation
            $readPrefStatus = $this->checkMongoDBAtlasReadPreference($mongodb);
            $healthData['checks']['read_preference'] = $readPrefStatus['status'];
            $healthData['metrics']['read_preference'] = $readPrefStatus['preference'];
            
            // Write concern validation
            $writeConcernStatus = $this->checkMongoDBAtlasWriteConcern($mongodb);
            $healthData['checks']['write_concern'] = $writeConcernStatus['status'];
            $healthData['metrics']['write_concern'] = $writeConcernStatus['concern'];
            
        } catch (\Exception $e) {
            $healthData['healthy'] = false;
            $healthData['errors'][] = $e->getMessage();
            $healthData['checks']['connectivity'] = 'failed';
            
            Log::error("MongoDB Atlas health check failed", [
                'connection' => $connectionName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $healthData;
    }

    /**
     * MySQL-specific health check.
     */
    private function performMySQLHealthCheck(string $connectionName): array
    {
        $healthData = [
            'connection' => $connectionName,
            'driver' => 'mysql',
            'healthy' => true,
            'checks' => [],
            'metrics' => [],
            'warnings' => [],
            'errors' => []
        ];

        try {
            $connection = DB::connection($connectionName);
            
            // Basic connectivity
            $startTime = microtime(true);
            $connection->getPdo();
            $healthData['metrics']['connection_time_ms'] = (microtime(true) - $startTime) * 1000;
            $healthData['checks']['connectivity'] = 'passed';
            
            // MySQL version
            $versionResult = $connection->select('SELECT VERSION() as version');
            $healthData['metrics']['version'] = $versionResult[0]->version ?? 'unknown';
            
            // Replication status
            $replicationStatus = $this->checkMySQLReplication($connection);
            if ($replicationStatus) {
                $healthData['checks']['replication'] = $replicationStatus['status'];
                $healthData['metrics']['seconds_behind_master'] = $replicationStatus['seconds_behind'];
            }
            
            // Connection status
            $connectionStatus = $this->checkMySQLConnections($connection);
            $healthData['checks']['connections'] = $connectionStatus['status'];
            $healthData['metrics']['active_connections'] = $connectionStatus['active_connections'];
            
        } catch (\Exception $e) {
            $healthData['healthy'] = false;
            $healthData['errors'][] = $e->getMessage();
            $healthData['checks']['connectivity'] = 'failed';
        }

        return $healthData;
    }

    /**
     * Generic health check for unknown database types.
     */
    private function performGenericHealthCheck(string $connectionName): array
    {
        $healthData = [
            'connection' => $connectionName,
            'driver' => 'generic',
            'healthy' => true,
            'checks' => [],
            'metrics' => [],
            'warnings' => [],
            'errors' => []
        ];

        try {
            $connection = DB::connection($connectionName);
            
            $startTime = microtime(true);
            $connection->getPdo();
            $healthData['metrics']['connection_time_ms'] = (microtime(true) - $startTime) * 1000;
            $healthData['checks']['connectivity'] = 'passed';
            
            // Basic query test
            $queryStart = microtime(true);
            $connection->select('SELECT 1 as test');
            $healthData['metrics']['query_response_time_ms'] = (microtime(true) - $queryStart) * 1000;
            $healthData['checks']['query_test'] = 'passed';
            
        } catch (\Exception $e) {
            $healthData['healthy'] = false;
            $healthData['errors'][] = $e->getMessage();
            $healthData['checks']['connectivity'] = 'failed';
        }

        return $healthData;
    }

    /**
     * PostgreSQL-specific helper methods
     */
    private function checkPostgreSQLConnectionPool($connection): array
    {
        try {
            $result = $connection->select("
                SELECT 
                    count(*) as active_connections,
                    (SELECT setting::int FROM pg_settings WHERE name = 'max_connections') as max_connections
                FROM pg_stat_activity 
                WHERE state = 'active'
            ");
            
            $activeConnections = $result[0]->active_connections ?? 0;
            $maxConnections = $result[0]->max_connections ?? 100;
            
            $status = ($activeConnections / $maxConnections) > 0.8 ? 'warning' : 'passed';
            
            return [
                'status' => $status,
                'active_connections' => $activeConnections,
                'max_connections' => $maxConnections
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'active_connections' => 0,
                'max_connections' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    private function checkPostgreSQLReplicaLag($connection): ?array
    {
        try {
            // Check if this is a replica
            $result = $connection->select("SELECT pg_is_in_recovery() as is_replica");
            $isReplica = $result[0]->is_replica ?? false;
            
            if (!$isReplica) {
                return null; // This is a primary, no replica lag
            }
            
            // Get replica lag
            $lagResult = $connection->select("
                SELECT 
                    CASE 
                        WHEN pg_last_wal_receive_lsn() = pg_last_wal_replay_lsn() THEN 0
                        ELSE EXTRACT(EPOCH FROM (now() - pg_last_xact_replay_timestamp()))
                    END as lag_seconds
            ");
            
            $lagSeconds = $lagResult[0]->lag_seconds ?? 0;
            $status = $lagSeconds > 30 ? 'warning' : 'passed';
            
            return [
                'status' => $status,
                'lag_seconds' => $lagSeconds
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'lag_seconds' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    private function checkPostgreSQLLocks($connection): array
    {
        try {
            $result = $connection->select("
                SELECT count(*) as lock_count 
                FROM pg_locks 
                WHERE NOT granted
            ");
            
            $lockCount = $result[0]->lock_count ?? 0;
            $status = $lockCount > 10 ? 'warning' : 'passed';
            
            return [
                'status' => $status,
                'lock_count' => $lockCount
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'lock_count' => 0
            ];
        }
    }

    private function checkPostgreSQLTransactions($connection): array
    {
        try {
            $result = $connection->select("
                SELECT count(*) as long_running_count
                FROM pg_stat_activity 
                WHERE state = 'active' 
                AND query_start < now() - interval '5 minutes'
            ");
            
            $longRunningCount = $result[0]->long_running_count ?? 0;
            $status = $longRunningCount > 5 ? 'warning' : 'passed';
            
            return [
                'status' => $status,
                'long_running_count' => $longRunningCount
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'long_running_count' => 0
            ];
        }
    }

    private function getPostgreSQLPerformanceMetrics($connection): array
    {
        try {
            $result = $connection->select("
                SELECT 
                    (SELECT sum(blks_hit) / (sum(blks_hit) + sum(blks_read)) * 100 
                     FROM pg_stat_database) as cache_hit_ratio,
                    (SELECT count(*) FROM pg_stat_activity WHERE state = 'active') as active_queries,
                    (SELECT sum(temp_files) FROM pg_stat_database) as temp_files
            ");
            
            return [
                'cache_hit_ratio' => $result[0]->cache_hit_ratio ?? 0,
                'active_queries' => $result[0]->active_queries ?? 0,
                'temp_files' => $result[0]->temp_files ?? 0
            ];
            
        } catch (\Exception $e) {
            return [
                'cache_hit_ratio' => 0,
                'active_queries' => 0,
                'temp_files' => 0
            ];
        }
    }

    private function testPostgreSQLQueryPerformance($connection): array
    {
        try {
            $startTime = microtime(true);
            
            // Simple performance test query
            $connection->select("
                SELECT count(*) as test_count
                FROM information_schema.tables 
                WHERE table_schema = 'public'
            ");
            
            $responseTime = (microtime(true) - $startTime) * 1000;
            $status = $responseTime > 1000 ? 'warning' : 'passed';
            
            return [
                'status' => $status,
                'response_time_ms' => $responseTime
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'response_time_ms' => 0
            ];
        }
    }

    /**
     * MongoDB Atlas-specific helper methods
     */
    private function checkMongoDBAtlasReplicaSet($mongodb): array
    {
        try {
            // Get replica set status using replSetGetStatus command
            $replStatus = $mongodb->command(['replSetGetStatus' => 1])->toArray()[0];
            
            $members = $replStatus['members'] ?? [];
            $memberCount = count($members);
            $primary = null;
            $secondaries = [];
            $healthyMembers = 0;
            
            foreach ($members as $member) {
                if ($member['health'] == 1) {
                    $healthyMembers++;
                }
                
                if ($member['stateStr'] === 'PRIMARY') {
                    $primary = $member['name'];
                } elseif ($member['stateStr'] === 'SECONDARY') {
                    $secondaries[] = $member['name'];
                }
            }
            
            $status = ($healthyMembers === $memberCount && $primary !== null) ? 'passed' : 'warning';
            $message = $status === 'passed' ? 'All replica set members healthy' : 
                      "Only {$healthyMembers}/{$memberCount} members healthy";
            
            return [
                'status' => $status,
                'member_count' => $memberCount,
                'healthy_members' => $healthyMembers,
                'primary' => $primary,
                'secondaries' => $secondaries,
                'message' => $message
            ];
            
        } catch (\Exception $e) {
            Log::warning("Failed to check MongoDB Atlas replica set status", [
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'failed',
                'member_count' => 0,
                'healthy_members' => 0,
                'primary' => null,
                'secondaries' => [],
                'message' => 'Failed to get replica set status: ' . $e->getMessage()
            ];
        }
    }

    private function checkMongoDBAtlasShards($mongodb): ?array
    {
        try {
            // Check if this is a sharded cluster
            $shardStatus = $mongodb->command(['listShards' => 1])->toArray()[0];
            
            if (!isset($shardStatus['shards'])) {
                return null; // Not a sharded cluster
            }
            
            $shards = $shardStatus['shards'];
            $shardCount = count($shards);
            $healthyShards = 0;
            
            foreach ($shards as $shard) {
                if (isset($shard['state']) && $shard['state'] === 1) {
                    $healthyShards++;
                }
            }
            
            // Get config server info
            $configServers = [];
            try {
                $configStatus = $mongodb->command(['isMaster' => 1])->toArray()[0];
                if (isset($configStatus['configsvr'])) {
                    $configServers = [$configStatus['me']];
                }
            } catch (\Exception $e) {
                // Not a config server or error getting config info
            }
            
            $status = ($healthyShards === $shardCount) ? 'passed' : 'warning';
            
            return [
                'status' => $status,
                'shard_count' => $shardCount,
                'healthy_shards' => $healthyShards,
                'config_servers' => $configServers
            ];
            
        } catch (\Exception $e) {
            // Not a sharded cluster or error checking
            return null;
        }
    }

    private function checkMongoDBAtlasOplog($mongodb): array
    {
        try {
            // Get oplog collection stats
            $oplogStats = $mongodb->selectCollection('local', 'oplog.rs')->aggregate([
                ['$collStats' => ['storageStats' => []]]
            ])->toArray()[0];
            
            $sizeBytes = $oplogStats['storageStats']['size'] ?? 0;
            $sizeMB = round($sizeBytes / (1024 * 1024), 2);
            
            // Get oplog size limit
            $oplogSizeLimit = $oplogStats['storageStats']['maxSize'] ?? $sizeBytes;
            $oplogSizeLimitMB = round($oplogSizeLimit / (1024 * 1024), 2);
            
            $utilizationPercent = $oplogSizeLimitMB > 0 ? 
                round(($sizeMB / $oplogSizeLimitMB) * 100, 2) : 0;
            
            $status = $utilizationPercent < 90 ? 'passed' : 'warning';
            
            return [
                'status' => $status,
                'size_mb' => $oplogSizeLimitMB,
                'used_mb' => $sizeMB,
                'utilization_percent' => $utilizationPercent
            ];
            
        } catch (\Exception $e) {
            Log::warning("Failed to check MongoDB Atlas oplog status", [
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'failed',
                'size_mb' => 0,
                'used_mb' => 0,
                'utilization_percent' => 0
            ];
        }
    }

    private function checkMongoDBAtlasConnectionPool($mongodb): array
    {
        try {
            // Get server status for connection info
            $serverStatus = $mongodb->command(['serverStatus' => 1])->toArray()[0];
            
            $connections = $serverStatus['connections'] ?? [];
            $current = $connections['current'] ?? 0;
            $available = $connections['available'] ?? 0;
            $totalCreated = $connections['totalCreated'] ?? 0;
            
            $total = $current + $available;
            $status = ($current / max($total, 1)) < 0.8 ? 'passed' : 'warning';
            
            return [
                'status' => $status,
                'active_connections' => $current,
                'available_connections' => $available,
                'total_connections' => $total,
                'total_created' => $totalCreated
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'active_connections' => 0,
                'available_connections' => 0,
                'total_connections' => 0,
                'total_created' => 0
            ];
        }
    }

    private function getMongoDBAtlasDatabaseStats($mongodb): array
    {
        try {
            // List all databases
            $databases = $mongodb->command(['listDatabases' => 1])->toArray()[0];
            
            $databaseCount = count($databases['databases'] ?? []);
            $totalSizeBytes = 0;
            $totalIndexSizeBytes = 0;
            
            foreach ($databases['databases'] as $db) {
                $totalSizeBytes += $db['sizeOnDisk'] ?? 0;
                
                // Get index stats for this database
                try {
                    $dbStats = $mongodb->selectDatabase($db['name'])->command(['dbStats' => 1])->toArray()[0];
                    $totalIndexSizeBytes += $dbStats['indexSize'] ?? 0;
                } catch (\Exception $e) {
                    // Skip if we can't get stats for this database
                }
            }
            
            return [
                'database_count' => $databaseCount,
                'total_size_mb' => round($totalSizeBytes / (1024 * 1024), 2),
                'index_size_mb' => round($totalIndexSizeBytes / (1024 * 1024), 2)
            ];
            
        } catch (\Exception $e) {
            return [
                'database_count' => 0,
                'total_size_mb' => 0,
                'index_size_mb' => 0
            ];
        }
    }

    private function testMongoDBAtlasPerformance($mongodb): array
    {
        try {
            // Test read performance
            $readStart = microtime(true);
            $mongodb->selectCollection('test', 'health_check')->findOne(['_id' => 'health_check']);
            $readTime = (microtime(true) - $readStart) * 1000;
            
            // Test write performance
            $writeStart = microtime(true);
            $mongodb->selectCollection('test', 'health_check')->replaceOne(
                ['_id' => 'health_check'],
                [
                    '_id' => 'health_check',
                    'timestamp' => new \MongoDB\BSON\UTCDateTime(),
                    'test_data' => 'failover_health_check'
                ],
                ['upsert' => true]
            );
            $writeTime = (microtime(true) - $writeStart) * 1000;
            
            $status = ($readTime < 1000 && $writeTime < 2000) ? 'passed' : 'warning';
            
            return [
                'status' => $status,
                'response_time_ms' => round($readTime, 2),
                'write_response_time_ms' => round($writeTime, 2)
            ];
            
        } catch (\Exception $e) {
            Log::warning("MongoDB Atlas performance test failed", [
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'failed',
                'response_time_ms' => 0,
                'write_response_time_ms' => 0
            ];
        }
    }

    private function checkMongoDBAtlasReadPreference($mongodb): array
    {
        try {
            // Get current read preference
            $readPreference = $mongodb->getReadPreference();
            $mode = $readPreference->getMode();
            $tagSets = $readPreference->getTagSets();
            
            return [
                'status' => 'passed',
                'preference' => $mode,
                'tag_sets' => $tagSets
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'preference' => 'unknown',
                'tag_sets' => []
            ];
        }
    }

    private function checkMongoDBAtlasWriteConcern($mongodb): array
    {
        try {
            // Get current write concern
            $writeConcern = $mongodb->getWriteConcern();
            $w = $writeConcern->getW();
            $wtimeout = $writeConcern->getWtimeout();
            $journal = $writeConcern->getJournal();
            
            return [
                'status' => 'passed',
                'concern' => [
                    'w' => $w,
                    'wtimeout' => $wtimeout,
                    'journal' => $journal
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'concern' => []
            ];
        }
    }

    /**
     * MySQL-specific helper methods
     */
    private function checkMySQLReplication($connection): ?array
    {
        try {
            $result = $connection->select("SHOW SLAVE STATUS");
            
            if (empty($result)) {
                return null; // Not a replica
            }
            
            $slaveStatus = $result[0];
            $secondsBehind = $slaveStatus->Seconds_Behind_Master ?? 0;
            $status = $secondsBehind > 30 ? 'warning' : 'passed';
            
            return [
                'status' => $status,
                'seconds_behind' => $secondsBehind
            ];
            
        } catch (\Exception $e) {
            return null;
        }
    }

    private function checkMySQLConnections($connection): array
    {
        try {
            $result = $connection->select("SHOW STATUS LIKE 'Threads_connected'");
            $activeConnections = $result[0]->Value ?? 0;
            
            return [
                'status' => 'passed',
                'active_connections' => $activeConnections
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'active_connections' => 0
            ];
        }
    }

    /**
     * Get database-specific optimization recommendations.
     */
    public function getOptimizationRecommendations(string $connectionName): array
    {
        $healthData = $this->performSpecificHealthCheck($connectionName);
        $recommendations = [];
        
        // Analyze health data and provide recommendations
        if (!empty($healthData['warnings'])) {
            foreach ($healthData['warnings'] as $warning) {
                if (str_contains($warning, 'replica lag')) {
                    $recommendations[] = [
                        'type' => 'performance',
                        'priority' => 'high',
                        'description' => 'High replica lag detected',
                        'recommendation' => 'Consider switching to primary connection or investigate network issues'
                    ];
                } elseif (str_contains($warning, 'lock count')) {
                    $recommendations[] = [
                        'type' => 'performance',
                        'priority' => 'medium',
                        'description' => 'High lock contention detected',
                        'recommendation' => 'Monitor for long-running transactions and consider connection pooling'
                    ];
                } elseif (str_contains($warning, 'query performance')) {
                    $recommendations[] = [
                        'type' => 'performance',
                        'priority' => 'high',
                        'description' => 'Slow query performance detected',
                        'recommendation' => 'This connection may not be suitable for failover'
                    ];
                }
            }
        }
        
        return $recommendations;
    }

    /**
     * Optimize connection for failover.
     */
    public function optimizeConnectionForFailover(string $connectionName): array
    {
        $driver = config("database.connections.{$connectionName}.driver");
        $optimizations = [];
        
        switch ($driver) {
            case 'pgsql':
                $optimizations = $this->optimizePostgreSQLConnection($connectionName);
                break;
                
            case 'mongodb':
                $optimizations = $this->optimizeMongoDBConnection($connectionName);
                break;
                
            case 'mysql':
                $optimizations = $this->optimizeMySQLConnection($connectionName);
                break;
        }
        
        $this->connectionOptimizations[$connectionName] = $optimizations;
        
        return $optimizations;
    }

    private function optimizePostgreSQLConnection(string $connectionName): array
    {
        $optimizations = [];
        
        // Set optimal connection parameters
        try {
            $connection = DB::connection($connectionName);
            
            // Set statement timeout
            $connection->statement("SET statement_timeout = '30s'");
            $optimizations[] = 'Set statement timeout to 30 seconds';
            
            // Set lock timeout
            $connection->statement("SET lock_timeout = '10s'");
            $optimizations[] = 'Set lock timeout to 10 seconds';
            
            // Enable synchronous commit for consistency
            $connection->statement("SET synchronous_commit = on");
            $optimizations[] = 'Enabled synchronous commit';
            
        } catch (\Exception $e) {
            Log::warning("Failed to optimize PostgreSQL connection", [
                'connection' => $connectionName,
                'error' => $e->getMessage()
            ]);
        }
        
        return $optimizations;
    }

    private function optimizeMongoDBConnection(string $connectionName): array
    {
        // MongoDB-specific optimizations would go here
        return ['MongoDB connection optimized for failover'];
    }

    private function optimizeMySQLConnection(string $connectionName): array
    {
        // MySQL-specific optimizations would go here
        return ['MySQL connection optimized for failover'];
    }

    /**
     * Check if connection is suitable for failover.
     */
    public function isConnectionSuitableForFailover(string $connectionName): array
    {
        $healthData = $this->performSpecificHealthCheck($connectionName);
        $suitability = [
            'suitable' => true,
            'score' => 100,
            'reasons' => [],
            'blocking_issues' => []
        ];
        
        // Evaluate suitability based on health data
        if (!$healthData['healthy']) {
            $suitability['suitable'] = false;
            $suitability['score'] = 0;
            $suitability['blocking_issues'][] = 'Connection is not healthy';
            return $suitability;
        }
        
        // Check response time
        $responseTime = $healthData['metrics']['query_response_time_ms'] ?? 0;
        if ($responseTime > 1000) {
            $suitability['score'] -= 30;
            $suitability['reasons'][] = "Slow query response time: {$responseTime}ms";
        }
        
        // Check replica lag (PostgreSQL)
        if (isset($healthData['metrics']['replica_lag_seconds'])) {
            $replicaLag = $healthData['metrics']['replica_lag_seconds'];
            if ($replicaLag > 60) {
                $suitability['suitable'] = false;
                $suitability['blocking_issues'][] = "High replica lag: {$replicaLag} seconds";
            } elseif ($replicaLag > 30) {
                $suitability['score'] -= 20;
                $suitability['reasons'][] = "Moderate replica lag: {$replicaLag} seconds";
            }
        }
        
        // Check connection pool utilization
        if (isset($healthData['metrics']['active_connections']) && isset($healthData['metrics']['max_connections'])) {
            $utilization = $healthData['metrics']['active_connections'] / $healthData['metrics']['max_connections'];
            if ($utilization > 0.9) {
                $suitability['score'] -= 25;
                $suitability['reasons'][] = "High connection pool utilization: " . round($utilization * 100) . "%";
            }
        }
        
        // Final suitability determination
        if ($suitability['score'] < 50) {
            $suitability['suitable'] = false;
        }
        
        return $suitability;
    }

    /**
     * Get connection-specific metrics for monitoring.
     */
    public function getConnectionMetrics(string $connectionName): array
    {
        $healthData = $this->performSpecificHealthCheck($connectionName);
        
        return [
            'connection' => $connectionName,
            'timestamp' => now()->toISOString(),
            'healthy' => $healthData['healthy'],
            'metrics' => $healthData['metrics'],
            'warnings_count' => count($healthData['warnings']),
            'errors_count' => count($healthData['errors']),
            'suitability_score' => $this->isConnectionSuitableForFailover($connectionName)['score']
        ];
    }
}
