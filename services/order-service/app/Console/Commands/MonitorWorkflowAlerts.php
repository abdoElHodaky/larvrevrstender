<?php

namespace App\Console\Commands;

use App\Services\WorkflowAlertingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorWorkflowAlerts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'workflow:monitor-alerts 
                            {--check-interval=60 : Interval in seconds between alert checks}
                            {--max-iterations=0 : Maximum iterations (0 for infinite)}
                            {--verbose : Show detailed output}';

    /**
     * The console command description.
     */
    protected $description = 'Monitor workflow metrics and trigger alerts when thresholds are exceeded';

    protected WorkflowAlertingService $alertingService;

    /**
     * Create a new command instance.
     */
    public function __construct(WorkflowAlertingService $alertingService)
    {
        parent::__construct();
        $this->alertingService = $alertingService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $checkInterval = (int) $this->option('check-interval');
        $maxIterations = (int) $this->option('max-iterations');
        $verbose = $this->option('verbose');

        $this->info("Starting workflow alert monitoring...");
        $this->info("Check interval: {$checkInterval} seconds");
        $this->info("Max iterations: " . ($maxIterations > 0 ? $maxIterations : 'infinite'));

        $iteration = 0;

        while (true) {
            $iteration++;
            
            if ($maxIterations > 0 && $iteration > $maxIterations) {
                $this->info("Reached maximum iterations ({$maxIterations}). Stopping.");
                break;
            }

            try {
                $startTime = microtime(true);
                
                if ($verbose) {
                    $this->line("Iteration {$iteration} - Checking alerts at " . now()->toDateTimeString());
                }

                // Check all alert conditions
                $triggeredAlerts = $this->alertingService->checkAlerts();

                $duration = round((microtime(true) - $startTime) * 1000, 2);

                if (!empty($triggeredAlerts)) {
                    $this->warn("🚨 {count($triggeredAlerts)} alert(s) triggered:");
                    
                    foreach ($triggeredAlerts as $alert) {
                        $severity = $alert['severity'];
                        $emoji = match ($severity) {
                            'critical' => '🔴',
                            'warning' => '🟡',
                            'info' => '🔵',
                            default => '⚪',
                        };
                        
                        $this->line("  {$emoji} [{$severity}] {$alert['type']}: {$alert['message']}");
                    }
                } else {
                    if ($verbose) {
                        $this->line("✅ No alerts triggered");
                    }
                }

                if ($verbose) {
                    $this->line("Check completed in {$duration}ms");
                    $this->displayAlertStatistics();
                }

                // Log monitoring activity
                Log::debug('Workflow alert monitoring check completed', [
                    'iteration' => $iteration,
                    'triggered_alerts' => count($triggeredAlerts),
                    'duration_ms' => $duration,
                    'alerts' => $triggeredAlerts,
                ]);

                // Sleep until next check
                if ($checkInterval > 0) {
                    sleep($checkInterval);
                }

            } catch (\Exception $e) {
                $this->error("Error during alert monitoring: {$e->getMessage()}");
                
                Log::error('Workflow alert monitoring failed', [
                    'iteration' => $iteration,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Sleep before retrying
                sleep(min($checkInterval, 30));
            }
        }

        $this->info("Workflow alert monitoring stopped after {$iteration} iterations.");
        return self::SUCCESS;
    }

    /**
     * Display current alert statistics
     */
    private function displayAlertStatistics(): void
    {
        try {
            $stats = $this->alertingService->getAlertStatistics();
            
            $this->newLine();
            $this->info("📊 Today's Alert Statistics:");
            
            $this->table(
                ['Severity', 'Count'],
                [
                    ['Total', $stats['today']['total']],
                    ['Critical', $stats['today']['critical']],
                    ['Warning', $stats['today']['warning']],
                    ['Info', $stats['today']['info']],
                ]
            );

            if (!empty($stats['by_type']) && array_sum($stats['by_type']) > 0) {
                $this->line("By Type:");
                $typeData = [];
                foreach ($stats['by_type'] as $type => $count) {
                    if ($count > 0) {
                        $typeData[] = [ucwords(str_replace('_', ' ', $type)), $count];
                    }
                }
                
                if (!empty($typeData)) {
                    $this->table(['Type', 'Count'], $typeData);
                }
            }

            $this->newLine();

        } catch (\Exception $e) {
            $this->warn("Could not retrieve alert statistics: {$e->getMessage()}");
        }
    }
}
