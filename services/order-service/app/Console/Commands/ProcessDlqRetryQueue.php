<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDlqRetry;
use App\Services\WorkflowDeadLetterQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessDlqRetryQueue extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'workflow:process-dlq-retry-queue 
                            {--batch-size=10 : Number of items to process in each batch}
                            {--max-retries=5 : Maximum number of retry attempts per item}
                            {--delay=30 : Delay in seconds between batches}';

    /**
     * The console command description.
     */
    protected $description = 'Process the dead letter queue retry queue in batches';

    protected WorkflowDeadLetterQueue $dlqService;

    /**
     * Create a new command instance.
     */
    public function __construct(WorkflowDeadLetterQueue $dlqService)
    {
        parent::__construct();
        $this->dlqService = $dlqService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $batchSize = (int) $this->option('batch-size');
        $maxRetries = (int) $this->option('max-retries');
        $delay = (int) $this->option('delay');

        $this->info("Starting DLQ retry queue processing...");
        $this->info("Batch size: {$batchSize}, Max retries: {$maxRetries}, Delay: {$delay}s");

        try {
            // Get statistics before processing
            $initialStats = $this->dlqService->getStatistics();
            $this->displayStatistics('Initial', $initialStats);

            // Process retry queue
            $results = $this->dlqService->processRetryQueue($batchSize);
            
            if (empty($results)) {
                $this->info('No items in retry queue to process.');
                return self::SUCCESS;
            }

            $this->info("Processing {count($results)} items from retry queue...");

            $successCount = 0;
            $failureCount = 0;
            $jobsDispatched = 0;

            foreach ($results as $result) {
                if ($result['success']) {
                    if ($result['action'] === 'retried') {
                        $successCount++;
                        $this->line("✅ Retried: {$result['failure_id']}");
                    } elseif ($result['action'] === 'dispatched') {
                        $jobsDispatched++;
                        $this->line("🚀 Dispatched job: {$result['failure_id']}");
                        
                        // Dispatch async job for this retry
                        ProcessDlqRetry::dispatch(
                            $result['failure_id'],
                            $result['retry_data'] ?? []
                        );
                    }
                } else {
                    $failureCount++;
                    $this->line("❌ Failed: {$result['failure_id']} - {$result['error']}");
                }
            }

            // Display summary
            $this->newLine();
            $this->info("Processing Summary:");
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Immediate Successes', $successCount],
                    ['Jobs Dispatched', $jobsDispatched],
                    ['Failures', $failureCount],
                    ['Total Processed', count($results)],
                ]
            );

            // Get statistics after processing
            if ($delay > 0) {
                $this->info("Waiting {$delay} seconds before final statistics...");
                sleep($delay);
            }

            $finalStats = $this->dlqService->getStatistics();
            $this->displayStatistics('Final', $finalStats);

            // Log the processing results
            Log::info('DLQ retry queue processing completed', [
                'batch_size' => $batchSize,
                'total_processed' => count($results),
                'immediate_successes' => $successCount,
                'jobs_dispatched' => $jobsDispatched,
                'failures' => $failureCount,
                'initial_stats' => $initialStats,
                'final_stats' => $finalStats,
            ]);

            $this->info('DLQ retry queue processing completed successfully!');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to process DLQ retry queue: {$e->getMessage()}");
            
            Log::error('DLQ retry queue processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'batch_size' => $batchSize,
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Display DLQ statistics in a formatted table
     */
    private function displayStatistics(string $label, array $stats): void
    {
        $this->newLine();
        $this->info("{$label} DLQ Statistics:");
        
        $tableData = [
            ['Pending Retries', $stats['pending_retries'] ?? 0],
            ['Manual Interventions', $stats['manual_interventions'] ?? 0],
            ['Daily Failures', $stats['daily_failures'] ?? 0],
            ['Resolution Rate', ($stats['resolution_rate'] ?? 0) . '%'],
        ];

        if (isset($stats['by_activity_type'])) {
            $this->table(['Metric', 'Count'], $tableData);
            
            $this->line('By Activity Type:');
            $activityData = [];
            foreach ($stats['by_activity_type'] as $type => $count) {
                $activityData[] = [ucfirst($type), $count];
            }
            $this->table(['Activity Type', 'Count'], $activityData);
        } else {
            $this->table(['Metric', 'Count'], $tableData);
        }
    }
}
