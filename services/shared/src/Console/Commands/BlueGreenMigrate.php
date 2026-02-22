<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BlueGreenMigrationService;
use Illuminate\Support\Facades\Log;

/**
 * Blue-Green Migration Command
 * 
 * Provides command-line interface for managing database migrations
 * during blue-green deployments with comprehensive validation and rollback capabilities.
 */
class BlueGreenMigrate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blue-green:migrate 
                            {environment : The environment color (blue|green)}
                            {--rollback= : Number of migrations to rollback}
                            {--status : Show migration status}
                            {--sync= : Synchronize migrations from another environment}
                            {--validate : Validate migration safety}
                            {--force : Force migration without validation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage database migrations for blue-green deployments';

    private BlueGreenMigrationService $migrationService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(BlueGreenMigrationService $migrationService)
    {
        parent::__construct();
        $this->migrationService = $migrationService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $environment = $this->argument('environment');
        
        // Validate environment color
        if (!in_array($environment, ['blue', 'green'])) {
            $this->error('Environment must be either "blue" or "green"');
            return 1;
        }

        try {
            // Handle different command options
            if ($this->option('status')) {
                return $this->showMigrationStatus($environment);
            }

            if ($this->option('rollback')) {
                return $this->rollbackMigrations($environment, (int)$this->option('rollback'));
            }

            if ($this->option('sync')) {
                return $this->synchronizeMigrations($this->option('sync'), $environment);
            }

            if ($this->option('validate')) {
                return $this->validateMigrations($environment);
            }

            // Default action: run migrations
            return $this->runMigrations($environment);

        } catch (\Exception $e) {
            $this->error('Command failed: ' . $e->getMessage());
            Log::error('Blue-green migration command failed', [
                'environment' => $environment,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Run migrations for the specified environment
     *
     * @param string $environment
     * @return int
     */
    private function runMigrations(string $environment): int
    {
        $this->info("🚀 Starting migrations for {$environment} environment...");
        
        // Validation unless forced
        if (!$this->option('force')) {
            $this->info('🔍 Validating migration safety...');
            
            $otherEnvironment = $environment === 'blue' ? 'green' : 'blue';
            if (!$this->migrationService->areMigrationsSafeForDeployment($otherEnvironment, $environment)) {
                $this->error('❌ Migration safety validation failed. Use --force to override.');
                return 1;
            }
            
            $this->info('✅ Migration safety validation passed');
        }

        // Show progress bar
        $this->info('📋 Running migrations...');
        
        $success = $this->migrationService->runMigrations($environment);
        
        if ($success) {
            $this->info("✅ Migrations completed successfully for {$environment} environment");
            
            // Show final status
            $this->showMigrationStatus($environment);
            
            return 0;
        } else {
            $this->error("❌ Migrations failed for {$environment} environment");
            $this->error('Check logs for detailed error information');
            return 1;
        }
    }

    /**
     * Show migration status for the environment
     *
     * @param string $environment
     * @return int
     */
    private function showMigrationStatus(string $environment): int
    {
        $this->info("📊 Migration Status for {$environment} Environment");
        $this->line('');

        $status = $this->migrationService->getMigrationStatus($environment);

        // Summary table
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Migrations', $status['total_migrations']],
                ['Completed', $status['completed']],
                ['Failed', $status['failed']],
                ['Running', $status['running']],
                ['Rolled Back', $status['rolled_back']],
                ['Last Migration', $status['last_migration_at'] ?? 'Never'],
            ]
        );

        // Detailed migration list if there are any
        if (!empty($status['migrations'])) {
            $this->line('');
            $this->info('📋 Migration Details:');
            
            $migrationData = [];
            foreach ($status['migrations'] as $migration) {
                $migrationData[] = [
                    $migration->migration_name,
                    $migration->status,
                    $migration->started_at ?? 'N/A',
                    $migration->execution_time_ms ? $migration->execution_time_ms . 'ms' : 'N/A',
                ];
            }

            $this->table(
                ['Migration', 'Status', 'Started At', 'Duration'],
                $migrationData
            );
        }

        return 0;
    }

    /**
     * Rollback migrations for the environment
     *
     * @param string $environment
     * @param int $steps
     * @return int
     */
    private function rollbackMigrations(string $environment, int $steps): int
    {
        $this->warn("⚠️  Rolling back {$steps} migration(s) for {$environment} environment...");
        
        if (!$this->confirm('Are you sure you want to rollback migrations? This action cannot be undone.')) {
            $this->info('Rollback cancelled');
            return 0;
        }

        $success = $this->migrationService->rollbackMigrations($environment, $steps);
        
        if ($success) {
            $this->info("✅ Rollback completed successfully for {$environment} environment");
            
            // Show updated status
            $this->showMigrationStatus($environment);
            
            return 0;
        } else {
            $this->error("❌ Rollback failed for {$environment} environment");
            return 1;
        }
    }

    /**
     * Synchronize migrations between environments
     *
     * @param string $sourceEnvironment
     * @param string $targetEnvironment
     * @return int
     */
    private function synchronizeMigrations(string $sourceEnvironment, string $targetEnvironment): int
    {
        // Validate source environment
        if (!in_array($sourceEnvironment, ['blue', 'green'])) {
            $this->error('Source environment must be either "blue" or "green"');
            return 1;
        }

        $this->info("🔄 Synchronizing migrations from {$sourceEnvironment} to {$targetEnvironment}...");
        
        $success = $this->migrationService->synchronizeMigrations($sourceEnvironment, $targetEnvironment);
        
        if ($success) {
            $this->info("✅ Migration synchronization completed successfully");
            
            // Show status for both environments
            $this->line('');
            $this->info("Source Environment ({$sourceEnvironment}):");
            $this->showMigrationStatus($sourceEnvironment);
            
            $this->line('');
            $this->info("Target Environment ({$targetEnvironment}):");
            $this->showMigrationStatus($targetEnvironment);
            
            return 0;
        } else {
            $this->error("❌ Migration synchronization failed");
            return 1;
        }
    }

    /**
     * Validate migrations for deployment safety
     *
     * @param string $environment
     * @return int
     */
    private function validateMigrations(string $environment): int
    {
        $this->info("🔍 Validating migration safety for {$environment} environment...");
        
        $otherEnvironment = $environment === 'blue' ? 'green' : 'blue';
        
        $isSafe = $this->migrationService->areMigrationsSafeForDeployment($otherEnvironment, $environment);
        
        if ($isSafe) {
            $this->info("✅ Migrations are safe for blue-green deployment");
            
            // Show status for both environments
            $this->line('');
            $this->info("Current Environment ({$otherEnvironment}):");
            $this->showMigrationStatus($otherEnvironment);
            
            $this->line('');
            $this->info("Target Environment ({$environment}):");
            $this->showMigrationStatus($environment);
            
            return 0;
        } else {
            $this->error("❌ Migrations are NOT safe for blue-green deployment");
            $this->error("Check migration status and resolve any failed or running migrations");
            
            // Show status for both environments to help with debugging
            $this->line('');
            $this->warn("Current Environment ({$otherEnvironment}):");
            $this->showMigrationStatus($otherEnvironment);
            
            $this->line('');
            $this->warn("Target Environment ({$environment}):");
            $this->showMigrationStatus($environment);
            
            return 1;
        }
    }
}
