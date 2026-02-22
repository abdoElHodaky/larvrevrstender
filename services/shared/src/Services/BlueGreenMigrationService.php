<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use Carbon\Carbon;

/**
 * Blue-Green Migration Coordination Service
 * 
 * Manages database migrations during blue-green deployments to ensure
 * zero-downtime deployments with backward compatibility validation.
 */
class BlueGreenMigrationService
{
    private string $currentEnvironment;
    private array $migrationConfig;
    
    public function __construct()
    {
        $this->currentEnvironment = env('ENVIRONMENT_COLOR', 'blue');
        $this->migrationConfig = config('blue_green.migrations', [
            'timeout' => 300, // 5 minutes
            'batch_size' => 100,
            'validation_enabled' => true,
            'rollback_enabled' => true,
        ]);
    }

    /**
     * Run migrations for the specified environment
     *
     * @param string $environmentColor
     * @param array $migrations
     * @return bool
     */
    public function runMigrations(string $environmentColor, array $migrations = []): bool
    {
        Log::info("Starting migrations for {$environmentColor} environment", [
            'environment' => $environmentColor,
            'migration_count' => count($migrations),
            'current_environment' => $this->currentEnvironment
        ]);

        if (empty($migrations)) {
            $migrations = $this->getPendingMigrations($environmentColor);
        }

        if (empty($migrations)) {
            Log::info("No pending migrations for {$environmentColor} environment");
            return true;
        }

        // Validate backward compatibility before running migrations
        if ($this->migrationConfig['validation_enabled']) {
            if (!$this->validateBackwardCompatibility($migrations)) {
                Log::error("Backward compatibility validation failed for {$environmentColor}");
                return false;
            }
        }

        DB::beginTransaction();
        
        try {
            foreach ($migrations as $migration) {
                if (!$this->runSingleMigration($migration, $environmentColor)) {
                    throw new \Exception("Migration {$migration} failed");
                }
            }
            
            DB::commit();
            Log::info("All migrations completed successfully for {$environmentColor}");
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Migration failed for {$environmentColor}: " . $e->getMessage(), [
                'environment' => $environmentColor,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Mark failed migrations
            $this->markMigrationsAsFailed($migrations, $environmentColor, $e->getMessage());
            
            return false;
        }
    }

    /**
     * Run a single migration with comprehensive tracking
     *
     * @param string $migrationName
     * @param string $environmentColor
     * @return bool
     */
    private function runSingleMigration(string $migrationName, string $environmentColor): bool
    {
        $startTime = microtime(true);
        
        try {
            // Record migration start
            $migrationId = $this->recordMigrationStart($migrationName, $environmentColor);
            
            // Execute the migration
            $this->executeMigration($migrationName);
            
            // Record successful completion
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            $this->recordMigrationCompletion($migrationId, $executionTime);
            
            Log::info("Migration completed successfully", [
                'migration' => $migrationName,
                'environment' => $environmentColor,
                'execution_time_ms' => $executionTime
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            $this->recordMigrationFailure($migrationName, $environmentColor, $e->getMessage(), $executionTime);
            
            Log::error("Migration failed", [
                'migration' => $migrationName,
                'environment' => $environmentColor,
                'error' => $e->getMessage(),
                'execution_time_ms' => $executionTime
            ]);
            
            throw $e;
        }
    }

    /**
     * Validate backward compatibility of migrations
     *
     * @param array $migrations
     * @return bool
     */
    private function validateBackwardCompatibility(array $migrations): bool
    {
        Log::info("Validating backward compatibility for migrations", [
            'migration_count' => count($migrations)
        ]);

        foreach ($migrations as $migration) {
            if (!$this->isMigrationBackwardCompatible($migration)) {
                Log::warning("Migration is not backward compatible", [
                    'migration' => $migration
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a migration is backward compatible
     *
     * @param string $migrationName
     * @return bool
     */
    private function isMigrationBackwardCompatible(string $migrationName): bool
    {
        // Get migration SQL
        $migrationSql = $this->getMigrationSql($migrationName);
        
        // Check for potentially breaking changes
        $breakingPatterns = [
            '/DROP\s+TABLE/i',
            '/DROP\s+COLUMN/i',
            '/ALTER\s+TABLE.*DROP/i',
            '/RENAME\s+TABLE/i',
            '/RENAME\s+COLUMN/i',
        ];

        foreach ($breakingPatterns as $pattern) {
            if (preg_match($pattern, $migrationSql)) {
                Log::warning("Potentially breaking SQL pattern found", [
                    'migration' => $migrationName,
                    'pattern' => $pattern
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Get pending migrations for an environment
     *
     * @param string $environmentColor
     * @return array
     */
    private function getPendingMigrations(string $environmentColor): array
    {
        // Get all available migrations
        $allMigrations = $this->getAllMigrations();
        
        // Get completed migrations for this environment
        $completedMigrations = DB::table('blue_green_migrations')
            ->where('environment_color', $environmentColor)
            ->where('status', 'completed')
            ->pluck('migration_name')
            ->toArray();

        // Return pending migrations
        return array_diff($allMigrations, $completedMigrations);
    }

    /**
     * Get all available migrations
     *
     * @return array
     */
    private function getAllMigrations(): array
    {
        $migrationPath = database_path('migrations');
        $files = glob($migrationPath . '/*.php');
        
        $migrations = [];
        foreach ($files as $file) {
            $migrations[] = basename($file, '.php');
        }
        
        sort($migrations);
        return $migrations;
    }

    /**
     * Execute a migration
     *
     * @param string $migrationName
     * @return void
     */
    private function executeMigration(string $migrationName): void
    {
        // This would typically call Laravel's migration system
        // For now, we'll simulate the execution
        $migrationPath = database_path("migrations/{$migrationName}.php");
        
        if (!file_exists($migrationPath)) {
            throw new \Exception("Migration file not found: {$migrationPath}");
        }

        // Include and execute the migration
        require_once $migrationPath;
        
        // Extract class name from file
        $className = $this->getMigrationClassName($migrationName);
        
        if (!class_exists($className)) {
            throw new \Exception("Migration class not found: {$className}");
        }

        $migration = new $className();
        $migration->up();
    }

    /**
     * Get migration class name from file name
     *
     * @param string $migrationName
     * @return string
     */
    private function getMigrationClassName(string $migrationName): string
    {
        // Remove timestamp prefix and convert to class name
        $parts = explode('_', $migrationName);
        if (count($parts) >= 4) {
            // Remove first 4 parts (timestamp and date)
            $classParts = array_slice($parts, 4);
            return 'Create' . implode('', array_map('ucfirst', $classParts)) . 'Table';
        }
        
        return 'Migration' . ucfirst(str_replace('_', '', $migrationName));
    }

    /**
     * Get migration SQL content
     *
     * @param string $migrationName
     * @return string
     */
    private function getMigrationSql(string $migrationName): string
    {
        $migrationPath = database_path("migrations/{$migrationName}.php");
        
        if (!file_exists($migrationPath)) {
            return '';
        }

        return file_get_contents($migrationPath);
    }

    /**
     * Record migration start
     *
     * @param string $migrationName
     * @param string $environmentColor
     * @return int
     */
    private function recordMigrationStart(string $migrationName, string $environmentColor): int
    {
        return DB::table('blue_green_migrations')->insertGetId([
            'migration_name' => $migrationName,
            'environment_color' => $environmentColor,
            'status' => 'running',
            'started_at' => Carbon::now(),
            'executed_by' => auth()->user()->email ?? 'system',
            'metadata' => json_encode([
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'server_name' => gethostname(),
            ]),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * Record migration completion
     *
     * @param int $migrationId
     * @param int $executionTimeMs
     * @return void
     */
    private function recordMigrationCompletion(int $migrationId, int $executionTimeMs): void
    {
        DB::table('blue_green_migrations')
            ->where('id', $migrationId)
            ->update([
                'status' => 'completed',
                'completed_at' => Carbon::now(),
                'execution_time_ms' => $executionTimeMs,
                'updated_at' => Carbon::now(),
            ]);
    }

    /**
     * Record migration failure
     *
     * @param string $migrationName
     * @param string $environmentColor
     * @param string $errorMessage
     * @param int $executionTimeMs
     * @return void
     */
    private function recordMigrationFailure(string $migrationName, string $environmentColor, string $errorMessage, int $executionTimeMs): void
    {
        DB::table('blue_green_migrations')->updateOrInsert(
            [
                'migration_name' => $migrationName,
                'environment_color' => $environmentColor,
            ],
            [
                'status' => 'failed',
                'error_message' => $errorMessage,
                'execution_time_ms' => $executionTimeMs,
                'completed_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        );
    }

    /**
     * Mark multiple migrations as failed
     *
     * @param array $migrations
     * @param string $environmentColor
     * @param string $errorMessage
     * @return void
     */
    private function markMigrationsAsFailed(array $migrations, string $environmentColor, string $errorMessage): void
    {
        foreach ($migrations as $migration) {
            $this->recordMigrationFailure($migration, $environmentColor, $errorMessage, 0);
        }
    }

    /**
     * Rollback migrations for an environment
     *
     * @param string $environmentColor
     * @param int $steps
     * @return bool
     */
    public function rollbackMigrations(string $environmentColor, int $steps = 1): bool
    {
        if (!$this->migrationConfig['rollback_enabled']) {
            Log::warning("Migration rollback is disabled");
            return false;
        }

        Log::info("Starting migration rollback for {$environmentColor} environment", [
            'environment' => $environmentColor,
            'steps' => $steps
        ]);

        // Get last completed migrations
        $migrationsToRollback = DB::table('blue_green_migrations')
            ->where('environment_color', $environmentColor)
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->limit($steps)
            ->pluck('migration_name')
            ->toArray();

        if (empty($migrationsToRollback)) {
            Log::info("No migrations to rollback for {$environmentColor}");
            return true;
        }

        DB::beginTransaction();
        
        try {
            foreach ($migrationsToRollback as $migration) {
                $this->rollbackSingleMigration($migration, $environmentColor);
            }
            
            DB::commit();
            Log::info("Migration rollback completed successfully for {$environmentColor}");
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Migration rollback failed for {$environmentColor}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Rollback a single migration
     *
     * @param string $migrationName
     * @param string $environmentColor
     * @return void
     */
    private function rollbackSingleMigration(string $migrationName, string $environmentColor): void
    {
        Log::info("Rolling back migration", [
            'migration' => $migrationName,
            'environment' => $environmentColor
        ]);

        // Execute rollback
        $migrationPath = database_path("migrations/{$migrationName}.php");
        
        if (file_exists($migrationPath)) {
            require_once $migrationPath;
            $className = $this->getMigrationClassName($migrationName);
            
            if (class_exists($className)) {
                $migration = new $className();
                $migration->down();
            }
        }

        // Update migration status
        DB::table('blue_green_migrations')
            ->where('migration_name', $migrationName)
            ->where('environment_color', $environmentColor)
            ->update([
                'status' => 'rolled_back',
                'updated_at' => Carbon::now(),
            ]);
    }

    /**
     * Get migration status for an environment
     *
     * @param string $environmentColor
     * @return array
     */
    public function getMigrationStatus(string $environmentColor): array
    {
        $migrations = DB::table('blue_green_migrations')
            ->where('environment_color', $environmentColor)
            ->orderBy('started_at', 'desc')
            ->get()
            ->toArray();

        $summary = [
            'environment' => $environmentColor,
            'total_migrations' => count($migrations),
            'completed' => 0,
            'failed' => 0,
            'running' => 0,
            'rolled_back' => 0,
            'last_migration_at' => null,
            'migrations' => $migrations,
        ];

        foreach ($migrations as $migration) {
            $summary[$migration->status]++;
            
            if ($migration->completed_at && (!$summary['last_migration_at'] || $migration->completed_at > $summary['last_migration_at'])) {
                $summary['last_migration_at'] = $migration->completed_at;
            }
        }

        return $summary;
    }

    /**
     * Check if migrations are safe for blue-green deployment
     *
     * @param string $fromEnvironment
     * @param string $toEnvironment
     * @return bool
     */
    public function areMigrationsSafeForDeployment(string $fromEnvironment, string $toEnvironment): bool
    {
        $fromStatus = $this->getMigrationStatus($fromEnvironment);
        $toStatus = $this->getMigrationStatus($toEnvironment);

        // Check if there are any failed migrations
        if ($fromStatus['failed'] > 0 || $toStatus['failed'] > 0) {
            Log::warning("Failed migrations detected", [
                'from_environment' => $fromEnvironment,
                'to_environment' => $toEnvironment,
                'from_failed' => $fromStatus['failed'],
                'to_failed' => $toStatus['failed']
            ]);
            return false;
        }

        // Check if there are any running migrations
        if ($fromStatus['running'] > 0 || $toStatus['running'] > 0) {
            Log::warning("Running migrations detected", [
                'from_environment' => $fromEnvironment,
                'to_environment' => $toEnvironment,
                'from_running' => $fromStatus['running'],
                'to_running' => $toStatus['running']
            ]);
            return false;
        }

        return true;
    }

    /**
     * Synchronize migrations between environments
     *
     * @param string $sourceEnvironment
     * @param string $targetEnvironment
     * @return bool
     */
    public function synchronizeMigrations(string $sourceEnvironment, string $targetEnvironment): bool
    {
        Log::info("Synchronizing migrations between environments", [
            'source' => $sourceEnvironment,
            'target' => $targetEnvironment
        ]);

        // Get migrations that exist in source but not in target
        $sourceMigrations = DB::table('blue_green_migrations')
            ->where('environment_color', $sourceEnvironment)
            ->where('status', 'completed')
            ->pluck('migration_name')
            ->toArray();

        $targetMigrations = DB::table('blue_green_migrations')
            ->where('environment_color', $targetEnvironment)
            ->where('status', 'completed')
            ->pluck('migration_name')
            ->toArray();

        $migrationsToSync = array_diff($sourceMigrations, $targetMigrations);

        if (empty($migrationsToSync)) {
            Log::info("No migrations to synchronize");
            return true;
        }

        return $this->runMigrations($targetEnvironment, $migrationsToSync);
    }
}
