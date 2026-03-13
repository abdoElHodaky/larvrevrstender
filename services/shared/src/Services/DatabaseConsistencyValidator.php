<?php

namespace Shared\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Shared\Facades\SharedLog;
use Carbon\Carbon;

/**
 * Database Consistency Validator
 * 
 * Validates data consistency across different database types during failover scenarios.
 * Handles PostgreSQL → PostgreSQL and PostgreSQL → MongoDB consistency validation.
 */
class DatabaseConsistencyValidator
{
    private array $config;
    private array $consistencyChecks;

    public function __construct()
    {
        $this->config = config('database-failover.consistency', []);
        $this->consistencyChecks = [];
    }

    /**
     * Validate data consistency between source and target connections.
     */
    public function validateConsistency(string $sourceConnection, string $targetConnection): array
    {
        $validationId = $this->generateValidationId();
        
        // Log to SharedLog for centralized monitoring
        SharedLog::databaseFailover('consistency_validation_started', [
            'validation_id' => $validationId,
            'source_connection' => $sourceConnection,
            'target_connection' => $targetConnection,
            'validation_type' => 'data_consistency'
        ]);

        Log::info("Starting data consistency validation", [
            'validation_id' => $validationId,
            'source' => $sourceConnection,
            'target' => $targetConnection
        ]);

        $result = [
            'validation_id' => $validationId,
            'source_connection' => $sourceConnection,
            'target_connection' => $targetConnection,
            'started_at' => now()->toISOString(),
            'consistent' => true,
            'checks' => [],
            'inconsistencies' => [],
            'warnings' => [],
            'errors' => []
        ];

        try {
            // Determine validation strategy based on database types
            $sourceDriver = config("database.connections.{$sourceConnection}.driver");
            $targetDriver = config("database.connections.{$targetConnection}.driver");
            
            $result['source_driver'] = $sourceDriver;
            $result['target_driver'] = $targetDriver;

            if ($sourceDriver === 'pgsql' && $targetDriver === 'pgsql') {
                $result = $this->validatePostgreSQLToPostgreSQL($sourceConnection, $targetConnection, $result);
            } elseif ($sourceDriver === 'pgsql' && $targetDriver === 'mongodb') {
                $result = $this->validatePostgreSQLToMongoDB($sourceConnection, $targetConnection, $result);
            } elseif ($sourceDriver === 'mongodb' && $targetDriver === 'pgsql') {
                $result = $this->validateMongoDBToPostgreSQL($sourceConnection, $targetConnection, $result);
            } else {
                $result['warnings'][] = "Unsupported consistency validation between {$sourceDriver} and {$targetDriver}";
            }

            $result['completed_at'] = now()->toISOString();
            $result['duration_ms'] = now()->diffInMilliseconds(Carbon::parse($result['started_at']));

            // Log completion to SharedLog
            SharedLog::databaseFailover('consistency_validation_completed', [
                'validation_id' => $validationId,
                'source_connection' => $sourceConnection,
                'target_connection' => $targetConnection,
                'consistent' => $result['consistent'],
                'duration_ms' => $result['duration_ms'],
                'inconsistencies_count' => count($result['inconsistencies']),
                'warnings_count' => count($result['warnings']),
                'checks_performed' => array_keys($result['checks'])
            ]);

        } catch (\Exception $e) {
            $result['consistent'] = false;
            $result['errors'][] = $e->getMessage();
            $result['completed_at'] = now()->toISOString();
            
            // Log validation failure to SharedLog
            SharedLog::databaseFailover('consistency_validation_failed', [
                'validation_id' => $validationId,
                'source_connection' => $sourceConnection,
                'target_connection' => $targetConnection,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            
            Log::error("Data consistency validation failed", [
                'validation_id' => $validationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        // Cache validation result
        Cache::put("consistency_validation_{$validationId}", $result, now()->addHours(24));

        return $result;
    }

    /**
     * Validate consistency between two PostgreSQL databases.
     */
    private function validatePostgreSQLToPostgreSQL(string $sourceConnection, string $targetConnection, array $result): array
    {
        $sourceDB = DB::connection($sourceConnection);
        $targetDB = DB::connection($targetConnection);

        // 1. Check table structure consistency
        $tableCheck = $this->validatePostgreSQLTableStructures($sourceDB, $targetDB);
        $result['checks']['table_structures'] = $tableCheck;
        
        if (!$tableCheck['consistent']) {
            $result['consistent'] = false;
            $result['inconsistencies'] = array_merge($result['inconsistencies'], $tableCheck['differences']);
        }

        // 2. Check sequence consistency
        $sequenceCheck = $this->validatePostgreSQLSequences($sourceDB, $targetDB);
        $result['checks']['sequences'] = $sequenceCheck;
        
        if (!$sequenceCheck['consistent']) {
            $result['consistent'] = false;
            $result['inconsistencies'] = array_merge($result['inconsistencies'], $sequenceCheck['differences']);
        }

        // 3. Check critical data consistency (configurable tables)
        $criticalTables = $this->config['critical_tables'] ?? ['users', 'auctions', 'bids', 'payments'];
        foreach ($criticalTables as $table) {
            $dataCheck = $this->validatePostgreSQLTableData($sourceDB, $targetDB, $table);
            $result['checks']["data_{$table}"] = $dataCheck;
            
            if (!$dataCheck['consistent']) {
                $result['consistent'] = false;
                $result['inconsistencies'] = array_merge($result['inconsistencies'], $dataCheck['differences']);
            }
        }

        // 4. Check replication lag
        $lagCheck = $this->checkPostgreSQLReplicationLag($sourceDB, $targetDB);
        $result['checks']['replication_lag'] = $lagCheck;
        
        if ($lagCheck['lag_seconds'] > ($this->config['max_acceptable_lag'] ?? 30)) {
            $result['warnings'][] = "High replication lag: {$lagCheck['lag_seconds']} seconds";
        }

        return $result;
    }

    /**
     * Validate consistency between PostgreSQL and MongoDB.
     */
    private function validatePostgreSQLToMongoDB(string $sourceConnection, string $targetConnection, array $result): array
    {
        $sourceDB = DB::connection($sourceConnection);
        $targetDB = DB::connection($targetConnection);
        $mongodb = $targetDB->getMongoDB();

        // 1. Check critical record counts
        $criticalTables = $this->config['critical_tables'] ?? ['users', 'auctions', 'bids'];
        foreach ($criticalTables as $table) {
            $countCheck = $this->validatePostgreSQLToMongoDBCounts($sourceDB, $mongodb, $table);
            $result['checks']["count_{$table}"] = $countCheck;
            
            if (!$countCheck['consistent']) {
                $result['consistent'] = false;
                $result['inconsistencies'] = array_merge($result['inconsistencies'], $countCheck['differences']);
            }
        }

        // 2. Check recent data synchronization
        $syncCheck = $this->validatePostgreSQLToMongoDBSync($sourceDB, $mongodb);
        $result['checks']['data_sync'] = $syncCheck;
        
        if (!$syncCheck['consistent']) {
            $result['consistent'] = false;
            $result['inconsistencies'] = array_merge($result['inconsistencies'], $syncCheck['differences']);
        }

        // 3. Check critical record integrity
        $integrityCheck = $this->validatePostgreSQLToMongoDBIntegrity($sourceDB, $mongodb);
        $result['checks']['data_integrity'] = $integrityCheck;
        
        if (!$integrityCheck['consistent']) {
            $result['consistent'] = false;
            $result['inconsistencies'] = array_merge($result['inconsistencies'], $integrityCheck['differences']);
        }

        return $result;
    }

    /**
     * Validate consistency between MongoDB and PostgreSQL.
     */
    private function validateMongoDBToPostgreSQL(string $sourceConnection, string $targetConnection, array $result): array
    {
        // This is essentially the reverse of PostgreSQL to MongoDB
        return $this->validatePostgreSQLToMongoDB($targetConnection, $sourceConnection, $result);
    }

    /**
     * PostgreSQL-specific validation methods
     */
    private function validatePostgreSQLTableStructures($sourceDB, $targetDB): array
    {
        try {
            // Get table structures from both databases
            $sourceTables = $this->getPostgreSQLTableStructures($sourceDB);
            $targetTables = $this->getPostgreSQLTableStructures($targetDB);
            
            $differences = [];
            $consistent = true;

            // Check for missing tables
            foreach ($sourceTables as $tableName => $sourceStructure) {
                if (!isset($targetTables[$tableName])) {
                    $differences[] = "Table '{$tableName}' exists in source but not in target";
                    $consistent = false;
                    continue;
                }

                // Check column differences
                $targetStructure = $targetTables[$tableName];
                foreach ($sourceStructure['columns'] as $columnName => $sourceColumn) {
                    if (!isset($targetStructure['columns'][$columnName])) {
                        $differences[] = "Column '{$tableName}.{$columnName}' exists in source but not in target";
                        $consistent = false;
                    } elseif ($sourceColumn['type'] !== $targetStructure['columns'][$columnName]['type']) {
                        $differences[] = "Column '{$tableName}.{$columnName}' type mismatch: source={$sourceColumn['type']}, target={$targetStructure['columns'][$columnName]['type']}";
                        $consistent = false;
                    }
                }
            }

            return [
                'consistent' => $consistent,
                'source_table_count' => count($sourceTables),
                'target_table_count' => count($targetTables),
                'differences' => $differences
            ];

        } catch (\Exception $e) {
            return [
                'consistent' => false,
                'error' => $e->getMessage(),
                'differences' => ["Failed to validate table structures: " . $e->getMessage()]
            ];
        }
    }

    private function getPostgreSQLTableStructures($db): array
    {
        $tables = [];
        
        // Get all tables in public schema
        $tableList = $db->select("
            SELECT table_name 
            FROM information_schema.tables 
            WHERE table_schema = 'public' 
            AND table_type = 'BASE TABLE'
        ");

        foreach ($tableList as $table) {
            $tableName = $table->table_name;
            
            // Get columns for this table
            $columns = $db->select("
                SELECT column_name, data_type, is_nullable, column_default
                FROM information_schema.columns 
                WHERE table_schema = 'public' 
                AND table_name = ?
                ORDER BY ordinal_position
            ", [$tableName]);

            $tableColumns = [];
            foreach ($columns as $column) {
                $tableColumns[$column->column_name] = [
                    'type' => $column->data_type,
                    'nullable' => $column->is_nullable === 'YES',
                    'default' => $column->column_default
                ];
            }

            $tables[$tableName] = [
                'columns' => $tableColumns
            ];
        }

        return $tables;
    }

    private function validatePostgreSQLSequences($sourceDB, $targetDB): array
    {
        try {
            $sourceSequences = $this->getPostgreSQLSequences($sourceDB);
            $targetSequences = $this->getPostgreSQLSequences($targetDB);
            
            $differences = [];
            $consistent = true;

            foreach ($sourceSequences as $sequenceName => $sourceValue) {
                if (!isset($targetSequences[$sequenceName])) {
                    $differences[] = "Sequence '{$sequenceName}' exists in source but not in target";
                    $consistent = false;
                } else {
                    $targetValue = $targetSequences[$sequenceName];
                    $lag = $sourceValue - $targetValue;
                    
                    if ($lag > ($this->config['max_sequence_lag'] ?? 100)) {
                        $differences[] = "Sequence '{$sequenceName}' lag too high: {$lag} (source: {$sourceValue}, target: {$targetValue})";
                        $consistent = false;
                    }
                }
            }

            return [
                'consistent' => $consistent,
                'source_sequences' => count($sourceSequences),
                'target_sequences' => count($targetSequences),
                'differences' => $differences
            ];

        } catch (\Exception $e) {
            return [
                'consistent' => false,
                'error' => $e->getMessage(),
                'differences' => ["Failed to validate sequences: " . $e->getMessage()]
            ];
        }
    }

    private function getPostgreSQLSequences($db): array
    {
        $sequences = [];
        
        $sequenceList = $db->select("
            SELECT sequence_name 
            FROM information_schema.sequences 
            WHERE sequence_schema = 'public'
        ");

        foreach ($sequenceList as $sequence) {
            $sequenceName = $sequence->sequence_name;
            
            try {
                $value = $db->select("SELECT last_value FROM {$sequenceName}")[0]->last_value;
                $sequences[$sequenceName] = $value;
            } catch (\Exception $e) {
                // Skip sequences we can't read
            }
        }

        return $sequences;
    }

    private function validatePostgreSQLTableData($sourceDB, $targetDB, string $tableName): array
    {
        try {
            // Check if table exists in both databases
            $sourceExists = $this->tableExists($sourceDB, $tableName);
            $targetExists = $this->tableExists($targetDB, $tableName);
            
            if (!$sourceExists || !$targetExists) {
                return [
                    'consistent' => false,
                    'differences' => ["Table '{$tableName}' does not exist in both databases"]
                ];
            }

            // Compare record counts
            $sourceCount = $sourceDB->table($tableName)->count();
            $targetCount = $targetDB->table($tableName)->count();
            
            $differences = [];
            $consistent = true;

            if ($sourceCount !== $targetCount) {
                $differences[] = "Record count mismatch in '{$tableName}': source={$sourceCount}, target={$targetCount}";
                $consistent = false;
            }

            // Sample recent records for detailed comparison
            $sampleSize = min(100, $sourceCount);
            if ($sampleSize > 0) {
                $sourceRecords = $sourceDB->table($tableName)
                    ->orderBy('id', 'desc')
                    ->limit($sampleSize)
                    ->get()
                    ->keyBy('id');

                $targetRecords = $targetDB->table($tableName)
                    ->orderBy('id', 'desc')
                    ->limit($sampleSize)
                    ->get()
                    ->keyBy('id');

                $mismatchCount = 0;
                foreach ($sourceRecords as $id => $sourceRecord) {
                    if (!isset($targetRecords[$id])) {
                        $mismatchCount++;
                    } else {
                        // Compare key fields (simplified)
                        $sourceArray = (array) $sourceRecord;
                        $targetArray = (array) $targetRecords[$id];
                        
                        if (json_encode($sourceArray) !== json_encode($targetArray)) {
                            $mismatchCount++;
                        }
                    }
                }

                if ($mismatchCount > 0) {
                    $differences[] = "Data mismatch in '{$tableName}': {$mismatchCount}/{$sampleSize} recent records differ";
                    $consistent = false;
                }
            }

            return [
                'consistent' => $consistent,
                'source_count' => $sourceCount,
                'target_count' => $targetCount,
                'sample_size' => $sampleSize,
                'differences' => $differences
            ];

        } catch (\Exception $e) {
            return [
                'consistent' => false,
                'error' => $e->getMessage(),
                'differences' => ["Failed to validate table data for '{$tableName}': " . $e->getMessage()]
            ];
        }
    }

    private function checkPostgreSQLReplicationLag($sourceDB, $targetDB): array
    {
        try {
            // Check if target is a replica
            $isReplica = $targetDB->select("SELECT pg_is_in_recovery() as is_replica")[0]->is_replica ?? false;
            
            if (!$isReplica) {
                return [
                    'is_replica' => false,
                    'lag_seconds' => 0,
                    'message' => 'Target is not a replica'
                ];
            }

            // Get replication lag
            $lagResult = $targetDB->select("
                SELECT 
                    CASE 
                        WHEN pg_last_wal_receive_lsn() = pg_last_wal_replay_lsn() THEN 0
                        ELSE EXTRACT(EPOCH FROM (now() - pg_last_xact_replay_timestamp()))
                    END as lag_seconds
            ");

            $lagSeconds = $lagResult[0]->lag_seconds ?? 0;

            return [
                'is_replica' => true,
                'lag_seconds' => $lagSeconds,
                'message' => "Replication lag: {$lagSeconds} seconds"
            ];

        } catch (\Exception $e) {
            return [
                'is_replica' => false,
                'lag_seconds' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * PostgreSQL to MongoDB validation methods
     */
    private function validatePostgreSQLToMongoDBCounts($sourceDB, $mongodb, string $tableName): array
    {
        try {
            // Get PostgreSQL count
            $pgCount = $sourceDB->table($tableName)->count();
            
            // Get MongoDB count (assuming collection name matches table name)
            $mongoCount = $mongodb->selectCollection($tableName)->countDocuments();
            
            $consistent = $pgCount === $mongoCount;
            $differences = [];
            
            if (!$consistent) {
                $differences[] = "Count mismatch for '{$tableName}': PostgreSQL={$pgCount}, MongoDB={$mongoCount}";
            }

            return [
                'consistent' => $consistent,
                'postgresql_count' => $pgCount,
                'mongodb_count' => $mongoCount,
                'differences' => $differences
            ];

        } catch (\Exception $e) {
            return [
                'consistent' => false,
                'error' => $e->getMessage(),
                'differences' => ["Failed to validate counts for '{$tableName}': " . $e->getMessage()]
            ];
        }
    }

    private function validatePostgreSQLToMongoDBSync($sourceDB, $mongodb): array
    {
        try {
            // Check sync status by comparing recent timestamps
            $recentThreshold = now()->subMinutes(5);
            
            $differences = [];
            $consistent = true;

            // Check users table/collection sync
            if ($this->tableExists($sourceDB, 'users')) {
                $recentPgUsers = $sourceDB->table('users')
                    ->where('updated_at', '>=', $recentThreshold)
                    ->count();

                $recentMongoUsers = $mongodb->selectCollection('users')
                    ->countDocuments([
                        'updated_at' => ['$gte' => new \MongoDB\BSON\UTCDateTime($recentThreshold)]
                    ]);

                if (abs($recentPgUsers - $recentMongoUsers) > 5) {
                    $differences[] = "Recent user sync mismatch: PostgreSQL={$recentPgUsers}, MongoDB={$recentMongoUsers}";
                    $consistent = false;
                }
            }

            return [
                'consistent' => $consistent,
                'checked_at' => now()->toISOString(),
                'threshold' => $recentThreshold->toISOString(),
                'differences' => $differences
            ];

        } catch (\Exception $e) {
            return [
                'consistent' => false,
                'error' => $e->getMessage(),
                'differences' => ["Failed to validate sync: " . $e->getMessage()]
            ];
        }
    }

    private function validatePostgreSQLToMongoDBIntegrity($sourceDB, $mongodb): array
    {
        try {
            $differences = [];
            $consistent = true;

            // Sample integrity check - compare a few critical records
            $sampleUsers = $sourceDB->table('users')
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get();

            foreach ($sampleUsers as $pgUser) {
                $mongoUser = $mongodb->selectCollection('users')
                    ->findOne(['id' => $pgUser->id]);

                if (!$mongoUser) {
                    $differences[] = "User ID {$pgUser->id} exists in PostgreSQL but not in MongoDB";
                    $consistent = false;
                } else {
                    // Check key fields
                    if ($pgUser->email !== $mongoUser['email']) {
                        $differences[] = "User ID {$pgUser->id} email mismatch: PG={$pgUser->email}, Mongo={$mongoUser['email']}";
                        $consistent = false;
                    }
                }
            }

            return [
                'consistent' => $consistent,
                'sample_size' => count($sampleUsers),
                'differences' => $differences
            ];

        } catch (\Exception $e) {
            return [
                'consistent' => false,
                'error' => $e->getMessage(),
                'differences' => ["Failed to validate integrity: " . $e->getMessage()]
            ];
        }
    }

    /**
     * Detect split-brain scenarios.
     */
    public function detectSplitBrain(array $connections): array
    {
        $splitBrainId = $this->generateValidationId();
        
        // Log split-brain detection start to SharedLog
        SharedLog::databaseFailover('split_brain_detection_started', [
            'split_brain_id' => $splitBrainId,
            'connections' => $connections,
            'connection_count' => count($connections)
        ]);

        Log::info("Starting split-brain detection", [
            'split_brain_id' => $splitBrainId,
            'connections' => $connections
        ]);

        $result = [
            'split_brain_id' => $splitBrainId,
            'connections' => $connections,
            'started_at' => now()->toISOString(),
            'split_brain_detected' => false,
            'active_writers' => [],
            'warnings' => [],
            'errors' => []
        ];

        try {
            foreach ($connections as $connectionName) {
                $driver = config("database.connections.{$connectionName}.driver");
                
                if ($driver === 'pgsql') {
                    $isWriter = $this->isPostgreSQLWriter($connectionName);
                    if ($isWriter) {
                        $result['active_writers'][] = $connectionName;
                    }
                } elseif ($driver === 'mongodb') {
                    $isWriter = $this->isMongoDBWriter($connectionName);
                    if ($isWriter) {
                        $result['active_writers'][] = $connectionName;
                    }
                }
            }

            // Split-brain detected if multiple writers of same type
            $pgWriters = array_filter($result['active_writers'], function($conn) {
                return config("database.connections.{$conn}.driver") === 'pgsql';
            });

            if (count($pgWriters) > 1) {
                $result['split_brain_detected'] = true;
                $result['warnings'][] = "Multiple PostgreSQL writers detected: " . implode(', ', $pgWriters);
                
                // Log critical split-brain detection to SharedLog
                SharedLog::databaseFailover('split_brain_detected', [
                    'split_brain_id' => $splitBrainId,
                    'multiple_writers' => $pgWriters,
                    'writer_count' => count($pgWriters),
                    'all_connections' => $connections,
                    'severity' => 'critical'
                ]);
            }

            $result['completed_at'] = now()->toISOString();
            
            // Log split-brain detection completion
            SharedLog::databaseFailover('split_brain_detection_completed', [
                'split_brain_id' => $splitBrainId,
                'split_brain_detected' => $result['split_brain_detected'],
                'active_writers' => $result['active_writers'],
                'connections_checked' => count($connections)
            ]);

        } catch (\Exception $e) {
            $result['errors'][] = $e->getMessage();
            $result['completed_at'] = now()->toISOString();
            
            Log::error("Split-brain detection failed", [
                'split_brain_id' => $splitBrainId,
                'error' => $e->getMessage()
            ]);
        }

        return $result;
    }

    private function isPostgreSQLWriter(string $connectionName): bool
    {
        try {
            $db = DB::connection($connectionName);
            
            // Check if this is a primary (not in recovery)
            $result = $db->select("SELECT pg_is_in_recovery() as is_replica")[0];
            return !$result->is_replica;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    private function isMongoDBWriter(string $connectionName): bool
    {
        try {
            $connection = DB::connection($connectionName);
            $mongodb = $connection->getMongoDB();
            
            // Check if we're connected to primary
            $isMaster = $mongodb->command(['isMaster' => 1])->toArray()[0];
            return $isMaster['ismaster'] ?? false;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Helper methods
     */
    private function tableExists($db, string $tableName): bool
    {
        try {
            $result = $db->select("
                SELECT COUNT(*) as count 
                FROM information_schema.tables 
                WHERE table_schema = 'public' 
                AND table_name = ?
            ", [$tableName]);
            
            return $result[0]->count > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function generateValidationId(): string
    {
        return 'consistency_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 8);
    }

    /**
     * Get validation history.
     */
    public function getValidationHistory(int $limit = 10): array
    {
        // This would typically query a database table
        // For now, return cached results
        $history = [];
        
        for ($i = 0; $i < $limit; $i++) {
            $key = "consistency_validation_history_{$i}";
            $validation = Cache::get($key);
            
            if ($validation) {
                $history[] = $validation;
            }
        }

        return $history;
    }
}
