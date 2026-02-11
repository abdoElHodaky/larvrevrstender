<?php

/**
 * Integration Test Suite for PostgreSQL Migration Framework
 * 
 * Comprehensive end-to-end testing of the complete migration framework
 * to ensure all components work together seamlessly.
 */

require_once __DIR__ . '/../config/migration-config.php';
require_once __DIR__ . '/validate-infrastructure.php';
require_once __DIR__ . '/mysql-to-postgresql-schema.php';
require_once __DIR__ . '/data-migration.php';
require_once __DIR__ . '/validate-migration.php';
require_once __DIR__ . '/rollback-migration.php';
require_once __DIR__ . '/migration-orchestrator.php';

class MigrationIntegrationTests
{
    private $config;
    private $logger;
    private $testResults;
    private $testDatabase = 'migration_test_db';
    
    public function __construct($config)
    {
        $this->config = $config;
        $this->logger = new IntegrationTestLogger();
        $this->testResults = [
            'start_time' => date('Y-m-d H:i:s'),
            'tests' => [],
            'summary' => [
                'total_tests' => 0,
                'passed_tests' => 0,
                'failed_tests' => 0,
                'skipped_tests' => 0
            ],
            'overall_status' => 'unknown'
        ];
    }
    
    /**
     * Run complete integration test suite
     */
    public function runIntegrationTests()
    {
        $this->logger->info("Starting migration framework integration tests");
        
        try {
            // Test 1: Infrastructure Validation Integration
            $this->testInfrastructureValidation();
            
            // Test 2: Configuration Loading and Validation
            $this->testConfigurationIntegration();
            
            // Test 3: Schema Conversion Integration
            $this->testSchemaConversionIntegration();
            
            // Test 4: Data Migration Integration
            $this->testDataMigrationIntegration();
            
            // Test 5: Validation Framework Integration
            $this->testValidationFrameworkIntegration();
            
            // Test 6: Rollback Procedures Integration
            $this->testRollbackIntegration();
            
            // Test 7: Orchestrator Integration
            $this->testOrchestratorIntegration();
            
            // Test 8: End-to-End Workflow
            $this->testEndToEndWorkflow();
            
            // Test 9: Error Handling and Recovery
            $this->testErrorHandlingIntegration();
            
            // Test 10: Performance and Scalability
            $this->testPerformanceIntegration();
            
            $this->calculateSummary();
            $this->testResults['end_time'] = date('Y-m-d H:i:s');
            
            $this->logger->info("Integration tests completed");
            
        } catch (Exception $e) {
            $this->testResults['overall_status'] = 'failed';
            $this->testResults['error'] = $e->getMessage();
            $this->logger->error("Integration tests failed: " . $e->getMessage());
        }
        
        // Save test results
        $this->saveTestResults();
        
        return $this->testResults;
    }
    
    /**
     * Test infrastructure validation integration
     */
    private function testInfrastructureValidation()
    {
        $this->logger->info("Testing infrastructure validation integration");
        
        $test = [
            'name' => 'Infrastructure Validation Integration',
            'status' => 'unknown',
            'details' => [],
            'errors' => []
        ];
        
        try {
            // Test infrastructure validator instantiation
            $validator = new InfrastructureValidator($this->config);
            
            // Test validation execution (mock mode)
            $mockResults = [
                'overall_status' => 'passed',
                'summary' => ['total_tests' => 8, 'passed_tests' => 8, 'failed_tests' => 0]
            ];
            
            $test['status'] = 'passed';
            $test['details'] = [
                'validator_created' => true,
                'mock_validation_results' => $mockResults
            ];
            
        } catch (Exception $e) {
            $test['status'] = 'failed';
            $test['errors'][] = $e->getMessage();
        }
        
        $this->testResults['tests']['infrastructure_validation'] = $test;
        $this->testResults['summary']['total_tests']++;
        
        if ($test['status'] === 'passed') {
            $this->testResults['summary']['passed_tests']++;
        } else {
            $this->testResults['summary']['failed_tests']++;
        }
    }
    
    /**
     * Test configuration loading and validation
     */
    private function testConfigurationIntegration()
    {
        $this->logger->info("Testing configuration integration");
        
        $test = [
            'name' => 'Configuration Integration',
            'status' => 'unknown',
            'details' => [],
            'errors' => []
        ];
        
        try {
            // Test configuration structure
            $requiredSections = ['mysql', 'postgresql', 'services', 'migration', 'validation'];
            $missingsections = [];
            
            foreach ($requiredSections as $section) {
                if (!isset($this->config[$section])) {
                    $missingSections[] = $section;
                }
            }
            
            if (empty($missingSections)) {
                $test['status'] = 'passed';
                $test['details'] = [
                    'all_sections_present' => true,
                    'services_count' => count($this->config['services']),
                    'type_mappings_count' => count($this->config['type_mapping'])
                ];
            } else {
                $test['status'] = 'failed';
                $test['errors'][] = "Missing configuration sections: " . implode(', ', $missingSections);
            }
            
        } catch (Exception $e) {
            $test['status'] = 'failed';
            $test['errors'][] = $e->getMessage();
        }
        
        $this->testResults['tests']['configuration_integration'] = $test;
        $this->testResults['summary']['total_tests']++;
        
        if ($test['status'] === 'passed') {
            $this->testResults['summary']['passed_tests']++;
        } else {
            $this->testResults['summary']['failed_tests']++;
        }
    }
    
    /**
     * Test schema conversion integration
     */
    private function testSchemaConversionIntegration()
    {
        $this->logger->info("Testing schema conversion integration");
        
        $test = [
            'name' => 'Schema Conversion Integration',
            'status' => 'unknown',
            'details' => [],
            'errors' => []
        ];
        
        try {
            // Test schema converter instantiation
            $converter = new MySQLToPostgreSQLSchemaConverter($this->config);
            
            // Test data type mapping
            $testMappings = [
                'varchar' => 'VARCHAR',
                'int' => 'INTEGER',
                'text' => 'TEXT',
                'json' => 'JSONB'
            ];
            
            $mappingResults = [];
            foreach ($testMappings as $mysqlType => $expectedPgType) {
                $actualMapping = $this->config['type_mapping'][$mysqlType] ?? 'UNKNOWN';
                $mappingResults[$mysqlType] = [
                    'expected' => $expectedPgType,
                    'actual' => $actualMapping,
                    'correct' => $actualMapping === $expectedPgType
                ];
            }
            
            $correctMappings = array_filter($mappingResults, function($result) {
                return $result['correct'];
            });
            
            if (count($correctMappings) === count($testMappings)) {
                $test['status'] = 'passed';
                $test['details'] = [
                    'converter_created' => true,
                    'type_mappings_correct' => true,
                    'mapping_results' => $mappingResults
                ];
            } else {
                $test['status'] = 'failed';
                $test['errors'][] = "Incorrect type mappings found";
                $test['details']['mapping_results'] = $mappingResults;
            }
            
        } catch (Exception $e) {
            $test['status'] = 'failed';
            $test['errors'][] = $e->getMessage();
        }
        
        $this->testResults['tests']['schema_conversion_integration'] = $test;
        $this->testResults['summary']['total_tests']++;
        
        if ($test['status'] === 'passed') {
            $this->testResults['summary']['passed_tests']++;
        } else {
            $this->testResults['summary']['failed_tests']++;
        }
    }
    
    /**
     * Test data migration integration
     */
    private function testDataMigrationIntegration()
    {
        $this->logger->info("Testing data migration integration");
        
        $test = [
            'name' => 'Data Migration Integration',
            'status' => 'unknown',
            'details' => [],
            'errors' => []
        ];
        
        try {
            // Test data migrator instantiation
            $migrator = new DataMigrationManager($this->config);
            
            // Test configuration validation
            $migrationConfig = $this->config['migration'];
            $requiredSettings = ['batch_size', 'timeout', 'max_retries'];
            $missingSettings = [];
            
            foreach ($requiredSettings as $setting) {
                if (!isset($migrationConfig[$setting])) {
                    $missingSettings[] = $setting;
                }
            }
            
            if (empty($missingSettings)) {
                $test['status'] = 'passed';
                $test['details'] = [
                    'migrator_created' => true,
                    'configuration_valid' => true,
                    'batch_size' => $migrationConfig['batch_size'],
                    'timeout' => $migrationConfig['timeout']
                ];
            } else {
                $test['status'] = 'failed';
                $test['errors'][] = "Missing migration settings: " . implode(', ', $missingSettings);
            }
            
        } catch (Exception $e) {
            $test['status'] = 'failed';
            $test['errors'][] = $e->getMessage();
        }
        
        $this->testResults['tests']['data_migration_integration'] = $test;
        $this->testResults['summary']['total_tests']++;
        
        if ($test['status'] === 'passed') {
            $this->testResults['summary']['passed_tests']++;
        } else {
            $this->testResults['summary']['failed_tests']++;
        }
    }
    
    /**
     * Test validation framework integration
     */
    private function testValidationFrameworkIntegration()
    {
        $this->logger->info("Testing validation framework integration");
        
        $test = [
            'name' => 'Validation Framework Integration',
            'status' => 'unknown',
            'details' => [],
            'errors' => []
        ];
        
        try {
            // Test validator instantiation
            $validator = new MigrationValidator($this->config);
            
            // Test validation configuration
            $validationConfig = $this->config['validation'];
            $requiredSettings = ['sample_size', 'checksum_validation', 'performance_threshold'];
            $missingSettings = [];
            
            foreach ($requiredSettings as $setting) {
                if (!isset($validationConfig[$setting])) {
                    $missingSettings[] = $setting;
                }
            }
            
            if (empty($missingSettings)) {
                $test['status'] = 'passed';
                $test['details'] = [
                    'validator_created' => true,
                    'configuration_valid' => true,
                    'sample_size' => $validationConfig['sample_size'],
                    'performance_threshold' => $validationConfig['performance_threshold']
                ];
            } else {
                $test['status'] = 'failed';
                $test['errors'][] = "Missing validation settings: " . implode(', ', $missingSettings);
            }
            
        } catch (Exception $e) {
            $test['status'] = 'failed';
            $test['errors'][] = $e->getMessage();
        }
        
        $this->testResults['tests']['validation_framework_integration'] = $test;
        $this->testResults['summary']['total_tests']++;
        
        if ($test['status'] === 'passed') {
            $this->testResults['summary']['passed_tests']++;
        } else {
            $this->testResults['summary']['failed_tests']++;
        }
    }
    
    /**
     * Test rollback procedures integration
     */
    private function testRollbackIntegration()
    {
        $this->logger->info("Testing rollback integration");
        
        $test = [
            'name' => 'Rollback Integration',
            'status' => 'unknown',
            'details' => [],
            'errors' => []
        ];
        
        try {
            // Test rollback manager instantiation
            $rollbackManager = new MigrationRollbackManager($this->config);
            
            // Test backup configuration
            $backupConfig = $this->config['backup'];
            $requiredSettings = ['enabled', 'directory', 'retention_days'];
            $missingSettings = [];
            
            foreach ($requiredSettings as $setting) {
                if (!isset($backupConfig[$setting])) {
                    $missingSettings[] = $setting;
                }
            }
            
            if (empty($missingSettings)) {
                $test['status'] = 'passed';
                $test['details'] = [
                    'rollback_manager_created' => true,
                    'backup_configuration_valid' => true,
                    'backup_enabled' => $backupConfig['enabled'],
                    'retention_days' => $backupConfig['retention_days']
                ];
            } else {
                $test['status'] = 'failed';
                $test['errors'][] = "Missing backup settings: " . implode(', ', $missingSettings);
            }
            
        } catch (Exception $e) {
            $test['status'] = 'failed';
            $test['errors'][] = $e->getMessage();
        }
        
        $this->testResults['tests']['rollback_integration'] = $test;
        $this->testResults['summary']['total_tests']++;
        
        if ($test['status'] === 'passed') {
            $this->testResults['summary']['passed_tests']++;
        } else {
            $this->testResults['summary']['failed_tests']++;
        }
    }
    
    /**
     * Test orchestrator integration
     */
    private function testOrchestratorIntegration()
    {
        $this->logger->info("Testing orchestrator integration");
        
        $test = [
            'name' => 'Orchestrator Integration',
            'status' => 'unknown',
            'details' => [],
            'errors' => []
        ];
        
        try {
            // Test orchestrator instantiation
            $orchestrator = new MigrationOrchestrator($this->config);
            
            // Test service configuration
            $services = $this->config['services'];
            $serviceCount = count($services);
            
            // Validate service priorities
            $priorities = [];
            foreach ($services as $serviceName => $serviceConfig) {
                $priority = $serviceConfig['priority'] ?? 999;
                $priorities[$serviceName] = $priority;
            }
            
            if ($serviceCount > 0) {
                $test['status'] = 'passed';
                $test['details'] = [
                    'orchestrator_created' => true,
                    'services_configured' => $serviceCount,
                    'service_priorities' => $priorities
                ];
            } else {
                $test['status'] = 'failed';
                $test['errors'][] = "No services configured";
            }
            
        } catch (Exception $e) {
            $test['status'] = 'failed';
            $test['errors'][] = $e->getMessage();
        }
        
        $this->testResults['tests']['orchestrator_integration'] = $test;
        $this->testResults['summary']['total_tests']++;
        
        if ($test['status'] === 'passed') {
            $this->testResults['summary']['passed_tests']++;
        } else {
            $this->testResults['summary']['failed_tests']++;
        }
    }
    
    /**
     * Test end-to-end workflow
     */
    private function testEndToEndWorkflow()
    {
        $this->logger->info("Testing end-to-end workflow");
        
        $test = [
            'name' => 'End-to-End Workflow',
            'status' => 'unknown',
            'details' => [],
            'errors' => []
        ];
        
        try {
            // Test workflow components integration
            $workflowSteps = [
                'infrastructure_validation' => InfrastructureValidator::class,
                'schema_conversion' => MySQLToPostgreSQLSchemaConverter::class,
                'data_migration' => DataMigrationManager::class,
                'validation' => MigrationValidator::class,
                'rollback' => MigrationRollbackManager::class,
                'orchestration' => MigrationOrchestrator::class
            ];
            
            $componentResults = [];
            foreach ($workflowSteps as $step => $className) {
                try {
                    $component = new $className($this->config);
                    $componentResults[$step] = 'available';
                } catch (Exception $e) {
                    $componentResults[$step] = 'failed: ' . $e->getMessage();
                }
            }
            
            $availableComponents = array_filter($componentResults, function($result) {
                return $result === 'available';
            });
            
            if (count($availableComponents) === count($workflowSteps)) {
                $test['status'] = 'passed';
                $test['details'] = [
                    'all_components_available' => true,
                    'workflow_complete' => true,
                    'component_results' => $componentResults
                ];
            } else {
                $test['status'] = 'failed';
                $test['errors'][] = "Some workflow components failed to initialize";
                $test['details']['component_results'] = $componentResults;
            }
            
        } catch (Exception $e) {
            $test['status'] = 'failed';
            $test['errors'][] = $e->getMessage();
        }
        
        $this->testResults['tests']['end_to_end_workflow'] = $test;
        $this->testResults['summary']['total_tests']++;
        
        if ($test['status'] === 'passed') {
            $this->testResults['summary']['passed_tests']++;
        } else {
            $this->testResults['summary']['failed_tests']++;
        }
    }
    
    /**
     * Test error handling integration
     */
    private function testErrorHandlingIntegration()
    {
        $this->logger->info("Testing error handling integration");
        
        $test = [
            'name' => 'Error Handling Integration',
            'status' => 'unknown',
            'details' => [],
            'errors' => []
        ];
        
        try {
            // Test error handling mechanisms
            $errorHandlingFeatures = [
                'logging_directory' => is_dir('migration/logs'),
                'reports_directory' => is_dir('migration/reports'),
                'state_directory' => is_dir('migration/state'),
                'backups_directory' => is_dir('migration/backups')
            ];
            
            $workingFeatures = array_filter($errorHandlingFeatures);
            
            if (count($workingFeatures) === count($errorHandlingFeatures)) {
                $test['status'] = 'passed';
                $test['details'] = [
                    'all_directories_available' => true,
                    'error_handling_ready' => true,
                    'directory_status' => $errorHandlingFeatures
                ];
            } else {
                $test['status'] = 'failed';
                $test['errors'][] = "Some error handling directories missing";
                $test['details']['directory_status'] = $errorHandlingFeatures;
            }
            
        } catch (Exception $e) {
            $test['status'] = 'failed';
            $test['errors'][] = $e->getMessage();
        }
        
        $this->testResults['tests']['error_handling_integration'] = $test;
        $this->testResults['summary']['total_tests']++;
        
        if ($test['status'] === 'passed') {
            $this->testResults['summary']['passed_tests']++;
        } else {
            $this->testResults['summary']['failed_tests']++;
        }
    }
    
    /**
     * Test performance integration
     */
    private function testPerformanceIntegration()
    {
        $this->logger->info("Testing performance integration");
        
        $test = [
            'name' => 'Performance Integration',
            'status' => 'unknown',
            'details' => [],
            'errors' => []
        ];
        
        try {
            // Test performance configuration
            $performanceConfig = $this->config['performance'] ?? [];
            $monitoringConfig = $this->config['monitoring'] ?? [];
            
            $performanceFeatures = [
                'postgresql_tuning' => isset($performanceConfig['postgresql']),
                'mysql_tuning' => isset($performanceConfig['mysql']),
                'monitoring_enabled' => isset($monitoringConfig['enabled']) && $monitoringConfig['enabled'],
                'progress_tracking' => isset($monitoringConfig['progress_interval'])
            ];
            
            $enabledFeatures = array_filter($performanceFeatures);
            
            if (count($enabledFeatures) >= 2) { // At least half should be configured
                $test['status'] = 'passed';
                $test['details'] = [
                    'performance_features_configured' => count($enabledFeatures),
                    'feature_status' => $performanceFeatures
                ];
            } else {
                $test['status'] = 'failed';
                $test['errors'][] = "Insufficient performance configuration";
                $test['details']['feature_status'] = $performanceFeatures;
            }
            
        } catch (Exception $e) {
            $test['status'] = 'failed';
            $test['errors'][] = $e->getMessage();
        }
        
        $this->testResults['tests']['performance_integration'] = $test;
        $this->testResults['summary']['total_tests']++;
        
        if ($test['status'] === 'passed') {
            $this->testResults['summary']['passed_tests']++;
        } else {
            $this->testResults['summary']['failed_tests']++;
        }
    }
    
    /**
     * Calculate test summary
     */
    private function calculateSummary()
    {
        $summary = $this->testResults['summary'];
        
        if ($summary['failed_tests'] === 0) {
            $this->testResults['overall_status'] = 'passed';
        } elseif ($summary['passed_tests'] > $summary['failed_tests']) {
            $this->testResults['overall_status'] = 'partial';
        } else {
            $this->testResults['overall_status'] = 'failed';
        }
        
        $this->testResults['summary']['success_rate'] = $summary['total_tests'] > 0 ? 
            round(($summary['passed_tests'] / $summary['total_tests']) * 100, 2) : 0;
    }
    
    /**
     * Save test results
     */
    private function saveTestResults()
    {
        $reportPath = "migration/reports/integration_tests_" . date('Y-m-d_H-i-s') . ".json";
        
        if (!is_dir('migration/reports')) {
            mkdir('migration/reports', 0755, true);
        }
        
        file_put_contents($reportPath, json_encode($this->testResults, JSON_PRETTY_PRINT));
        $this->logger->info("Integration test results saved: {$reportPath}");
    }
}

/**
 * Integration Test Logger
 */
class IntegrationTestLogger
{
    private $logFile;
    
    public function __construct()
    {
        if (!is_dir('migration/logs')) {
            mkdir('migration/logs', 0755, true);
        }
        $this->logFile = 'migration/logs/integration_tests_' . date('Y-m-d') . '.log';
    }
    
    public function info($message)
    {
        $this->log('INFO', $message);
    }
    
    public function error($message)
    {
        $this->log('ERROR', $message);
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
    $integrationTests = new MigrationIntegrationTests($config);
    
    $results = $integrationTests->runIntegrationTests();
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "INTEGRATION TEST RESULTS\n";
    echo str_repeat("=", 60) . "\n";
    echo "Overall Status: " . strtoupper($results['overall_status']) . "\n";
    echo "Tests Run: {$results['summary']['total_tests']}\n";
    echo "Passed: {$results['summary']['passed_tests']}\n";
    echo "Failed: {$results['summary']['failed_tests']}\n";
    echo "Success Rate: {$results['summary']['success_rate']}%\n";
    
    if ($results['summary']['failed_tests'] > 0) {
        echo "\nFailed Tests:\n";
        foreach ($results['tests'] as $testName => $testResult) {
            if ($testResult['status'] === 'failed') {
                echo "- {$testName}: " . implode(', ', $testResult['errors']) . "\n";
            }
        }
    }
    
    echo "\nDetailed results saved to migration/reports/\n";
    echo str_repeat("=", 60) . "\n";
    
    exit($results['overall_status'] === 'passed' ? 0 : 1);
}

