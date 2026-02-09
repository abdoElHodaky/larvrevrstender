<?php

/**
 * MySQL Baseline Report Generator
 * 
 * Generates comprehensive baseline performance report from MySQL benchmarking data
 */

require_once __DIR__ . '/../config/migration-config.php';

class BaselineReportGenerator
{
    private $config;
    private $logger;
    private $mysqlConnection;
    
    public function __construct($config)
    {
        $this->config = $config;
        $this->logger = new BaselineLogger();
        $this->initializeMySQLConnection();
    }
    
    private function initializeMySQLConnection()
    {
        try {
            $this->mysqlConnection = new PDO(
                "mysql:host={$this->config['mysql']['host']};port={$this->config['mysql']['port']};charset=utf8mb4",
                $this->config['mysql']['username'],
                $this->config['mysql']['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (Exception $e) {
            $this->logger->error("Failed to connect to MySQL: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Generate comprehensive baseline report
     */
    public function generateBaselineReport()
    {
        $this->logger->info("Starting baseline report generation");
        
        $report = [
            'generated_at' => date('Y-m-d H:i:s'),
            'mysql_version' => $this->getMySQLVersion(),
            'executive_summary' => [],
            'database_analysis' => [],
            'performance_analysis' => [],
            'compatibility_analysis' => [],
            'migration_recommendations' => []
        ];
        
        try {
            // Executive Summary
            $report['executive_summary'] = $this->generateExecutiveSummary();
            
            // Database Analysis
            $report['database_analysis'] = $this->analyzeDatabases();
            
            // Performance Analysis
            $report['performance_analysis'] = $this->analyzePerformance();
            
            // Compatibility Analysis
            $report['compatibility_analysis'] = $this->analyzeCompatibility();
            
            // Migration Recommendations
            $report['migration_recommendations'] = $this->generateMigrationRecommendations($report);
            
            // Save report
            $this->saveReport($report);
            
            $this->logger->info("Baseline report generation completed");
            
        } catch (Exception $e) {
            $this->logger->error("Baseline report generation failed: " . $e->getMessage());
            throw $e;
        }
        
        return $report;
    }
    
    /**
     * Get MySQL version information
     */
    private function getMySQLVersion()
    {
        $stmt = $this->mysqlConnection->query("SELECT VERSION()");
        return $stmt->fetchColumn();
    }
    
    /**
     * Generate executive summary
     */
    private function generateExecutiveSummary()
    {
        $this->logger->info("Generating executive summary");
        
        $summary = [
            'total_services' => 0,
            'total_databases' => 0,
            'total_size_mb' => 0,
            'largest_database' => null,
            'total_tables' => 0,
            'migration_complexity' => 'unknown'
        ];
        
        $services = array_keys($this->config['services']);
        $summary['total_services'] = count($services);
        
        $totalSize = 0;
        $largestDb = ['name' => '', 'size' => 0];
        $totalTables = 0;
        
        foreach ($services as $serviceName) {
            $serviceConfig = $this->config['services'][$serviceName];
            $database = $serviceConfig['mysql_database'];
            
            if ($this->databaseExists($database)) {
                $summary['total_databases']++;
                
                $dbSize = $this->getDatabaseSize($database);
                $totalSize += $dbSize;
                
                if ($dbSize > $largestDb['size']) {
                    $largestDb = ['name' => $database, 'size' => $dbSize];
                }
                
                $tableCount = $this->getTableCount($database);
                $totalTables += $tableCount;
            }
        }
        
        $summary['total_size_mb'] = round($totalSize, 2);
        $summary['largest_database'] = $largestDb;
        $summary['total_tables'] = $totalTables;
        $summary['migration_complexity'] = $this->assessMigrationComplexity($totalSize, $totalTables);
        
        return $summary;
    }
    
    /**
     * Analyze all service databases
     */
    private function analyzeDatabases()
    {
        $this->logger->info("Analyzing databases");
        
        $analysis = [
            'databases' => [],
            'summary' => [
                'total_size_mb' => 0,
                'total_tables' => 0,
                'total_indexes' => 0,
                'avg_table_size_mb' => 0
            ]
        ];
        
        foreach ($this->config['services'] as $serviceName => $serviceConfig) {
            $database = $serviceConfig['mysql_database'];
            
            if ($this->databaseExists($database)) {
                $dbAnalysis = $this->analyzeSingleDatabase($database, $serviceName);
                $analysis['databases'][$serviceName] = $dbAnalysis;
                
                $analysis['summary']['total_size_mb'] += $dbAnalysis['size_mb'];
                $analysis['summary']['total_tables'] += $dbAnalysis['table_count'];
                $analysis['summary']['total_indexes'] += $dbAnalysis['index_count'];
            }
        }
        
        if ($analysis['summary']['total_tables'] > 0) {
            $analysis['summary']['avg_table_size_mb'] = round(
                $analysis['summary']['total_size_mb'] / $analysis['summary']['total_tables'], 
                2
            );
        }
        
        return $analysis;
    }
    
    /**
     * Analyze single database
     */
    private function analyzeSingleDatabase($database, $serviceName)
    {
        $analysis = [
            'database' => $database,
            'service' => $serviceName,
            'size_mb' => 0,
            'table_count' => 0,
            'index_count' => 0,
            'largest_tables' => [],
            'storage_engines' => [],
            'character_sets' => [],
            'collations' => []
        ];
        
        // Database size
        $analysis['size_mb'] = $this->getDatabaseSize($database);
        
        // Table analysis
        $stmt = $this->mysqlConnection->prepare("
            SELECT 
                table_name,
                engine,
                table_rows,
                ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb,
                table_collation
            FROM information_schema.tables 
            WHERE table_schema = ? AND table_type = 'BASE TABLE'
            ORDER BY (data_length + index_length) DESC
        ");
        $stmt->execute([$database]);
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $analysis['table_count'] = count($tables);
        $analysis['largest_tables'] = array_slice($tables, 0, 5); // Top 5 largest tables
        
        // Storage engines
        $engines = array_column($tables, 'engine');
        $analysis['storage_engines'] = array_count_values($engines);
        
        // Collations
        $collations = array_column($tables, 'table_collation');
        $analysis['collations'] = array_count_values($collations);
        
        // Index count
        $stmt = $this->mysqlConnection->prepare("
            SELECT COUNT(DISTINCT index_name) as index_count
            FROM information_schema.statistics 
            WHERE table_schema = ?
        ");
        $stmt->execute([$database]);
        $analysis['index_count'] = $stmt->fetchColumn();
        
        return $analysis;
    }
    
    /**
     * Analyze performance characteristics
     */
    private function analyzePerformance()
    {
        $this->logger->info("Analyzing performance");
        
        $analysis = [
            'query_performance' => [],
            'connection_stats' => [],
            'resource_usage' => [],
            'slow_queries' => []
        ];
        
        try {
            // Query performance from performance_schema (if available)
            $analysis['query_performance'] = $this->analyzeQueryPerformance();
            
            // Connection statistics
            $analysis['connection_stats'] = $this->analyzeConnectionStats();
            
            // Resource usage
            $analysis['resource_usage'] = $this->analyzeResourceUsage();
            
            // Slow queries
            $analysis['slow_queries'] = $this->analyzeSlowQueries();
            
        } catch (Exception $e) {
            $this->logger->warning("Performance analysis partially failed: " . $e->getMessage());
            $analysis['error'] = $e->getMessage();
        }
        
        return $analysis;
    }
    
    /**
     * Analyze query performance
     */
    private function analyzeQueryPerformance()
    {
        try {
            // Check if performance_schema is enabled
            $stmt = $this->mysqlConnection->query("SHOW VARIABLES LIKE 'performance_schema'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['Value'] !== 'ON') {
                return ['status' => 'performance_schema_disabled'];
            }
            
            // Get top queries by execution time
            $stmt = $this->mysqlConnection->query("
                SELECT 
                    DIGEST_TEXT,
                    COUNT_STAR as execution_count,
                    AVG_TIMER_WAIT/1000000000 as avg_time_seconds,
                    SUM_TIMER_WAIT/1000000000 as total_time_seconds
                FROM performance_schema.events_statements_summary_by_digest 
                WHERE DIGEST_TEXT IS NOT NULL
                ORDER BY SUM_TIMER_WAIT DESC 
                LIMIT 10
            ");
            
            return [
                'status' => 'available',
                'top_queries' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
            
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Analyze connection statistics
     */
    private function analyzeConnectionStats()
    {
        $stats = [];
        
        $connectionVars = [
            'max_connections',
            'max_used_connections',
            'threads_connected',
            'threads_running',
            'connection_errors_max_connections'
        ];
        
        foreach ($connectionVars as $var) {
            try {
                $stmt = $this->mysqlConnection->prepare("SHOW STATUS LIKE ?");
                $stmt->execute([$var]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result) {
                    $stats[$var] = $result['Value'];
                }
            } catch (Exception $e) {
                $stats[$var] = 'unavailable';
            }
        }
        
        return $stats;
    }
    
    /**
     * Analyze resource usage
     */
    private function analyzeResourceUsage()
    {
        $usage = [];
        
        $resourceVars = [
            'innodb_buffer_pool_size',
            'innodb_buffer_pool_pages_total',
            'innodb_buffer_pool_pages_free',
            'key_buffer_size',
            'query_cache_size',
            'tmp_table_size',
            'max_heap_table_size'
        ];
        
        foreach ($resourceVars as $var) {
            try {
                $stmt = $this->mysqlConnection->prepare("SHOW VARIABLES LIKE ?");
                $stmt->execute([$var]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result) {
                    $usage[$var] = $result['Value'];
                }
            } catch (Exception $e) {
                $usage[$var] = 'unavailable';
            }
        }
        
        return $usage;
    }
    
    /**
     * Analyze slow queries
     */
    private function analyzeSlowQueries()
    {
        try {
            $stmt = $this->mysqlConnection->query("SHOW VARIABLES LIKE 'slow_query_log'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['Value'] !== 'ON') {
                return ['status' => 'slow_query_log_disabled'];
            }
            
            $stmt = $this->mysqlConnection->query("SHOW STATUS LIKE 'Slow_queries'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'status' => 'enabled',
                'slow_query_count' => $result['Value']
            ];
            
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Analyze PostgreSQL compatibility
     */
    private function analyzeCompatibility()
    {
        $this->logger->info("Analyzing PostgreSQL compatibility");
        
        $analysis = [
            'data_types' => [],
            'indexes' => [],
            'constraints' => [],
            'compatibility_score' => 0,
            'migration_issues' => []
        ];
        
        $totalIssues = 0;
        $totalItems = 0;
        
        foreach ($this->config['services'] as $serviceName => $serviceConfig) {
            $database = $serviceConfig['mysql_database'];
            
            if ($this->databaseExists($database)) {
                $dbCompatibility = $this->analyzeDbCompatibility($database);
                $analysis['data_types'][$serviceName] = $dbCompatibility['data_types'];
                $analysis['indexes'][$serviceName] = $dbCompatibility['indexes'];
                $analysis['constraints'][$serviceName] = $dbCompatibility['constraints'];
                
                $totalIssues += $dbCompatibility['issues'];
                $totalItems += $dbCompatibility['total_items'];
                
                if (!empty($dbCompatibility['migration_issues'])) {
                    $analysis['migration_issues'][$serviceName] = $dbCompatibility['migration_issues'];
                }
            }
        }
        
        if ($totalItems > 0) {
            $analysis['compatibility_score'] = round(((($totalItems - $totalIssues) / $totalItems) * 100), 2);
        }
        
        return $analysis;
    }
    
    /**
     * Analyze database compatibility
     */
    private function analyzeDbCompatibility($database)
    {
        $compatibility = [
            'data_types' => [],
            'indexes' => [],
            'constraints' => [],
            'issues' => 0,
            'total_items' => 0,
            'migration_issues' => []
        ];
        
        // Analyze data types
        $stmt = $this->mysqlConnection->prepare("
            SELECT data_type, COUNT(*) as count
            FROM information_schema.columns 
            WHERE table_schema = ?
            GROUP BY data_type
        ");
        $stmt->execute([$database]);
        $dataTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($dataTypes as $type) {
            $pgType = $this->config['type_mapping'][$type['data_type']] ?? 'UNKNOWN';
            $compatibility['data_types'][$type['data_type']] = [
                'count' => $type['count'],
                'postgresql_equivalent' => $pgType,
                'needs_conversion' => $pgType === 'UNKNOWN' || in_array($type['data_type'], ['enum', 'set'])
            ];
            
            $compatibility['total_items'] += $type['count'];
            
            if ($compatibility['data_types'][$type['data_type']]['needs_conversion']) {
                $compatibility['issues'] += $type['count'];
                $compatibility['migration_issues'][] = "Data type '{$type['data_type']}' requires special handling";
            }
        }
        
        // Analyze indexes
        $stmt = $this->mysqlConnection->prepare("
            SELECT index_type, COUNT(DISTINCT index_name) as count
            FROM information_schema.statistics 
            WHERE table_schema = ?
            GROUP BY index_type
        ");
        $stmt->execute([$database]);
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($indexes as $index) {
            $pgIndex = $this->config['index_mapping'][$index['index_type']] ?? 'UNKNOWN';
            $compatibility['indexes'][$index['index_type']] = [
                'count' => $index['count'],
                'postgresql_equivalent' => $pgIndex,
                'needs_conversion' => in_array($index['index_type'], ['FULLTEXT', 'SPATIAL'])
            ];
            
            $compatibility['total_items'] += $index['count'];
            
            if ($compatibility['indexes'][$index['index_type']]['needs_conversion']) {
                $compatibility['issues'] += $index['count'];
                $compatibility['migration_issues'][] = "Index type '{$index['index_type']}' requires conversion";
            }
        }
        
        return $compatibility;
    }
    
    /**
     * Generate migration recommendations
     */
    private function generateMigrationRecommendations($report)
    {
        $this->logger->info("Generating migration recommendations");
        
        $recommendations = [
            'priority_order' => [],
            'performance_expectations' => [],
            'risk_assessment' => [],
            'preparation_steps' => []
        ];
        
        // Priority order based on size and complexity
        $dbSizes = [];
        foreach ($report['database_analysis']['databases'] as $service => $db) {
            $dbSizes[$service] = $db['size_mb'];
        }
        asort($dbSizes); // Start with smallest databases
        
        $recommendations['priority_order'] = array_keys($dbSizes);
        
        // Performance expectations
        $totalSize = $report['executive_summary']['total_size_mb'];
        if ($totalSize < 1000) { // < 1GB
            $recommendations['performance_expectations'] = [
                'migration_time' => '2-4 hours',
                'expected_improvements' => ['JSON performance', 'Full-text search'],
                'potential_concerns' => ['Initial query optimization needed']
            ];
        } elseif ($totalSize < 10000) { // < 10GB
            $recommendations['performance_expectations'] = [
                'migration_time' => '4-8 hours',
                'expected_improvements' => ['JSON performance', 'Concurrent reads', 'OLAP queries'],
                'potential_concerns' => ['Connection pooling adjustment', 'Index optimization']
            ];
        } else { // > 10GB
            $recommendations['performance_expectations'] = [
                'migration_time' => '8-16 hours',
                'expected_improvements' => ['Significant OLAP improvements', 'Better concurrent performance'],
                'potential_concerns' => ['Extended migration window', 'Careful batch size tuning']
            ];
        }
        
        // Risk assessment
        $compatibilityScore = $report['compatibility_analysis']['compatibility_score'];
        if ($compatibilityScore > 90) {
            $recommendations['risk_assessment'] = ['level' => 'Low', 'description' => 'High compatibility, minimal issues expected'];
        } elseif ($compatibilityScore > 75) {
            $recommendations['risk_assessment'] = ['level' => 'Medium', 'description' => 'Some compatibility issues, manageable with preparation'];
        } else {
            $recommendations['risk_assessment'] = ['level' => 'High', 'description' => 'Significant compatibility issues, extensive testing required'];
        }
        
        // Preparation steps
        $recommendations['preparation_steps'] = [
            'Establish performance baselines with current benchmarking',
            'Test migration scripts with sample data',
            'Prepare rollback procedures and test them',
            'Set up monitoring and alerting for migration process',
            'Plan maintenance windows based on estimated migration times',
            'Coordinate with application teams for service dependencies'
        ];
        
        return $recommendations;
    }
    
    // Helper methods
    private function databaseExists($database)
    {
        try {
            $stmt = $this->mysqlConnection->prepare("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?");
            $stmt->execute([$database]);
            return $stmt->fetchColumn() !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function getDatabaseSize($database)
    {
        $stmt = $this->mysqlConnection->prepare("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
            FROM information_schema.tables 
            WHERE table_schema = ?
        ");
        $stmt->execute([$database]);
        return (float)$stmt->fetchColumn();
    }
    
    private function getTableCount($database)
    {
        $stmt = $this->mysqlConnection->prepare("
            SELECT COUNT(*) 
            FROM information_schema.tables 
            WHERE table_schema = ? AND table_type = 'BASE TABLE'
        ");
        $stmt->execute([$database]);
        return (int)$stmt->fetchColumn();
    }
    
    private function assessMigrationComplexity($totalSize, $totalTables)
    {
        if ($totalSize < 100 && $totalTables < 50) {
            return 'Low';
        } elseif ($totalSize < 1000 && $totalTables < 200) {
            return 'Medium';
        } else {
            return 'High';
        }
    }
    
    private function saveReport($report)
    {
        // Save as JSON
        $jsonPath = "migration/reports/mysql_baseline_report_" . date('Y-m-d_H-i-s') . ".json";
        file_put_contents($jsonPath, json_encode($report, JSON_PRETTY_PRINT));
        
        // Save as Markdown
        $mdPath = "migration/assessment/mysql-baseline-report.md";
        $this->saveMarkdownReport($report, $mdPath);
        
        $this->logger->info("Baseline report saved: {$jsonPath} and {$mdPath}");
    }
    
    private function saveMarkdownReport($report, $path)
    {
        $md = "# MySQL Baseline Performance Report\n\n";
        $md .= "*Generated: {$report['generated_at']}*\n";
        $md .= "*MySQL Version: {$report['mysql_version']}*\n\n";
        
        // Executive Summary
        $summary = $report['executive_summary'];
        $md .= "## Executive Summary\n\n";
        $md .= "- **Total Services Analyzed**: {$summary['total_services']} microservices\n";
        $md .= "- **Total Database Size**: {$summary['total_size_mb']} MB\n";
        $md .= "- **Largest Database**: {$summary['largest_database']['name']} ({$summary['largest_database']['size']} MB)\n";
        $md .= "- **Total Tables**: {$summary['total_tables']}\n";
        $md .= "- **Migration Complexity**: {$summary['migration_complexity']}\n\n";
        
        // Database Analysis
        $md .= "## Database Size Analysis\n\n";
        $md .= "| Service | Database | Size (MB) | Tables | Indexes |\n";
        $md .= "|---------|----------|-----------|--------|---------|\n";
        
        foreach ($report['database_analysis']['databases'] as $service => $db) {
            $md .= "| {$service} | {$db['database']} | {$db['size_mb']} | {$db['table_count']} | {$db['index_count']} |\n";
        }
        
        // Compatibility Analysis
        $compat = $report['compatibility_analysis'];
        $md .= "\n## PostgreSQL Compatibility Analysis\n\n";
        $md .= "- **Overall Compatibility Score**: {$compat['compatibility_score']}%\n\n";
        
        if (!empty($compat['migration_issues'])) {
            $md .= "### Migration Issues Identified\n\n";
            foreach ($compat['migration_issues'] as $service => $issues) {
                $md .= "**{$service}:**\n";
                foreach ($issues as $issue) {
                    $md .= "- {$issue}\n";
                }
                $md .= "\n";
            }
        }
        
        // Migration Recommendations
        $rec = $report['migration_recommendations'];
        $md .= "## Migration Recommendations\n\n";
        $md .= "### Priority Order\n";
        foreach ($rec['priority_order'] as $i => $service) {
            $md .= ($i + 1) . ". {$service}\n";
        }
        
        $md .= "\n### Performance Expectations\n";
        $perf = $rec['performance_expectations'];
        $md .= "- **Estimated Migration Time**: {$perf['migration_time']}\n";
        $md .= "- **Expected Improvements**: " . implode(', ', $perf['expected_improvements']) . "\n";
        $md .= "- **Potential Concerns**: " . implode(', ', $perf['potential_concerns']) . "\n";
        
        $md .= "\n### Risk Assessment\n";
        $risk = $rec['risk_assessment'];
        $md .= "- **Risk Level**: {$risk['level']}\n";
        $md .= "- **Description**: {$risk['description']}\n";
        
        $md .= "\n### Preparation Steps\n";
        foreach ($rec['preparation_steps'] as $step) {
            $md .= "- {$step}\n";
        }
        
        $md .= "\n---\n\n";
        $md .= "*Report generated by PostgreSQL Migration Framework*\n";
        
        file_put_contents($path, $md);
    }
}

/**
 * Baseline Logger
 */
class BaselineLogger
{
    private $logFile;
    
    public function __construct()
    {
        if (!is_dir('migration/logs')) {
            mkdir('migration/logs', 0755, true);
        }
        $this->logFile = 'migration/logs/baseline_report_' . date('Y-m-d') . '.log';
    }
    
    public function info($message)
    {
        $this->log('INFO', $message);
    }
    
    public function error($message)
    {
        $this->log('ERROR', $message);
    }
    
    public function warning($message)
    {
        $this->log('WARNING', $message);
    }
    
    private function log($level, $message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$level}] {$timestamp} - {$message}\n";
        
        echo $logMessage;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $config = require __DIR__ . '/../config/migration-config.php';
    $generator = new BaselineReportGenerator($config);
    
    try {
        $report = $generator->generateBaselineReport();
        
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "MYSQL BASELINE REPORT GENERATED\n";
        echo str_repeat("=", 60) . "\n";
        echo "Services Analyzed: {$report['executive_summary']['total_services']}\n";
        echo "Total Size: {$report['executive_summary']['total_size_mb']} MB\n";
        echo "Compatibility Score: {$report['compatibility_analysis']['compatibility_score']}%\n";
        echo "Migration Complexity: {$report['executive_summary']['migration_complexity']}\n";
        echo "\nReports saved to:\n";
        echo "- migration/reports/ (JSON format)\n";
        echo "- migration/assessment/mysql-baseline-report.md (Markdown)\n";
        echo str_repeat("=", 60) . "\n";
        
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
}

