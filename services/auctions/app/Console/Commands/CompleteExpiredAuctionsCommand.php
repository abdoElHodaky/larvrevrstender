<?php

namespace App\Console\Commands;

use App\Jobs\CompleteExpiredAuctionsJob;
use App\Services\AuctionCompletionService;
use Illuminate\Console\Command;

/**
 * Complete Expired Auctions Command
 *
 * Console command to manually trigger auction completion or get stats.
 */
class CompleteExpiredAuctionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'auctions:complete-expired 
                            {--stats : Show completion statistics only}
                            {--queue : Queue the job instead of running synchronously}';

    /**
     * The console command description.
     */
    protected $description = 'Complete expired auctions and create orders';

    /**
     * Execute the console command.
     */
    public function handle(AuctionCompletionService $completionService): int
    {
        if ($this->option('stats')) {
            return $this->showStats($completionService);
        }

        if ($this->option('queue')) {
            return $this->queueJob();
        }

        return $this->runSynchronously($completionService);
    }

    /**
     * Show completion statistics
     */
    private function showStats(AuctionCompletionService $completionService): int
    {
        $this->info('Getting auction completion statistics...');

        $stats = $completionService->getCompletionStats();

        if (isset($stats['error'])) {
            $this->error('Failed to get stats: ' . $stats['error']);
            return 1;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Active Auctions', $stats['active_auctions']],
                ['Expired Auctions Pending', $stats['expired_auctions_pending']],
                ['Last Check', $stats['last_check']],
            ]
        );

        return 0;
    }

    /**
     * Queue the completion job
     */
    private function queueJob(): int
    {
        $this->info('Queueing expired auctions completion job...');

        CompleteExpiredAuctionsJob::dispatch();

        $this->info('Job queued successfully. Check logs for results.');
        return 0;
    }

    /**
     * Run completion synchronously
     */
    private function runSynchronously(AuctionCompletionService $completionService): int
    {
        $this->info('Processing expired auctions...');

        $results = $completionService->processExpiredAuctions();

        $this->info("Processed: {$results['processed']} auctions");
        $this->info("Completed: {$results['completed']} auctions");

        if ($results['failed'] > 0) {
            $this->warn("Failed: {$results['failed']} auctions");
            
            if (!empty($results['errors'])) {
                $this->error('Errors:');
                foreach ($results['errors'] as $error) {
                    $this->error("  - {$error}");
                }
            }
        }

        $this->info('Auction completion finished.');
        return $results['failed'] > 0 ? 1 : 0;
    }
}
