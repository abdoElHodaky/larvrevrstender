<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\BidEvaluation;
use App\Events\WinnerSelectedEvent;
use App\RPC\Adapters\UserServiceAdapter;
use App\RPC\Adapters\NotificationServiceAdapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Shared\Core\BaseService;
use App\Services\Contracts\WinnerSelectionServiceInterface;

/**
 * Winner Selection Service
 * 
 * Core service responsible for evaluating bids and selecting winners
 * using multi-criteria decision analysis.
 */
class WinnerSelectionService extends BaseService implements WinnerSelectionServiceInterface
{
    private UserServiceAdapter $userService;
    private NotificationServiceAdapter $notificationService;
    private BidEvaluationService $bidEvaluationService;

    public function __construct(
        UserServiceAdapter $userService,
        NotificationServiceAdapter $notificationService,
        BidEvaluationService $bidEvaluationService
    ) {
        $this->userService = $userService;
        $this->notificationService = $notificationService;
        $this->bidEvaluationService = $bidEvaluationService;
    }

    /**
     * Select winner for an auction using multi-criteria evaluation.
     */
    public function selectWinner(Auction $auction): WinnerSelectionResult
    {
        try {
            DB::beginTransaction();

            Log::info('Starting winner selection process', [
                'auction_id' => $auction->id,
                'auction_title' => $auction->title
            ]);

            // Step 1: Get all valid bids for the auction
            $validBids = $this->getValidBids($auction);
            
            if ($validBids->isEmpty()) {
                return new WinnerSelectionResult(
                    success: false,
                    message: 'No valid bids found for this auction',
                    winner: null,
                    evaluations: collect()
                );
            }

            // Step 2: Evaluate all bids
            $evaluations = $this->evaluateBids($validBids, $auction);

            // Step 3: Rank evaluations by composite score
            $rankedEvaluations = $this->rankEvaluations($evaluations);

            // Step 4: Determine winner (handle tie-breaking)
            $winner = $this->determineWinner($rankedEvaluations);

            if (!$winner) {
                return new WinnerSelectionResult(
                    success: false,
                    message: 'Unable to determine winner',
                    winner: null,
                    evaluations: $rankedEvaluations
                );
            }

            // Step 5: Update auction with winner information
            $this->updateAuctionWithWinner($auction, $winner);

            // Step 6: Send notifications
            $this->sendWinnerNotifications($auction, $winner, $rankedEvaluations);

            // Step 7: Fire winner selected event for downstream processing
            $this->fireWinnerSelectedEvent($auction, $winner, $rankedEvaluations);

            DB::commit();

            Log::info('Winner selection completed successfully', [
                'auction_id' => $auction->id,
                'winner_bid_id' => $winner->bid_id,
                'winner_score' => $winner->composite_score
            ]);

            return new WinnerSelectionResult(
                success: true,
                message: 'Winner selected successfully',
                winner: $winner,
                evaluations: $rankedEvaluations
            );

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Winner selection failed', [
                'auction_id' => $auction->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new WinnerSelectionResult(
                success: false,
                message: 'Winner selection failed: ' . $e->getMessage(),
                winner: null,
                evaluations: collect()
            );
        }
    }

    /**
     * Get all valid bids for the auction.
     */
    private function getValidBids(Auction $auction): Collection
    {
        return Bid::where('auction_id', $auction->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->where('submitted_at', '<=', $auction->ends_at)
            ->with(['attachments', 'user'])
            ->get();
    }

    /**
     * Evaluate all bids using multi-criteria analysis.
     */
    private function evaluateBids(Collection $bids, Auction $auction): Collection
    {
        $evaluations = collect();

        foreach ($bids as $bid) {
            try {
                $evaluation = $this->bidEvaluationService->evaluateBid($bid, $auction);
                $evaluations->push($evaluation);
            } catch (\Exception $e) {
                Log::warning('Failed to evaluate bid', [
                    'bid_id' => $bid->id,
                    'auction_id' => $auction->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $evaluations;
    }

    /**
     * Rank evaluations by composite score and handle ties.
     */
    private function rankEvaluations(Collection $evaluations): Collection
    {
        // Sort by composite score (highest first), then by submission time (earliest first)
        $sortedEvaluations = $evaluations->sortBy([
            ['composite_score', 'desc'],
            ['bid.submitted_at', 'asc']
        ]);

        $rank = 1;
        $previousScore = null;
        $sameRankCount = 0;

        foreach ($sortedEvaluations as $evaluation) {
            if ($previousScore !== null && $evaluation->composite_score < $previousScore) {
                $rank += $sameRankCount;
                $sameRankCount = 1;
            } else {
                $sameRankCount++;
            }

            $evaluation->rank = $rank;
            $evaluation->save();
            
            $previousScore = $evaluation->composite_score;
        }

        return $sortedEvaluations;
    }

    /**
     * Determine the winner from ranked evaluations.
     */
    private function determineWinner(Collection $rankedEvaluations): ?BidEvaluation
    {
        $topEvaluations = $rankedEvaluations->where('rank', 1);

        if ($topEvaluations->isEmpty()) {
            return null;
        }

        // If there's only one top evaluation, that's the winner
        if ($topEvaluations->count() === 1) {
            $winner = $topEvaluations->first();
            $winner->markApproved();
            return $winner;
        }

        // Handle tie-breaking for multiple top evaluations
        return $this->handleTieBreaking($topEvaluations);
    }

    /**
     * Handle tie-breaking when multiple bids have the same score.
     */
    private function handleTieBreaking(Collection $tiedEvaluations): ?BidEvaluation
    {
        // Tie-breaking criteria (in order of priority):
        // 1. Highest supplier score
        // 2. Earliest submission time
        // 3. Lowest bid amount (if reverse auction)

        $winner = $tiedEvaluations
            ->sortBy([
                ['supplier_score', 'desc'],
                ['bid.submitted_at', 'asc'],
                ['bid.amount', 'asc']
            ])
            ->first();

        if ($winner) {
            $winner->markApproved();
            
            Log::info('Tie-breaking applied', [
                'tied_evaluations_count' => $tiedEvaluations->count(),
                'winner_bid_id' => $winner->bid_id,
                'tie_breaking_criteria' => [
                    'supplier_score' => $winner->supplier_score,
                    'submission_time' => $winner->bid->submitted_at,
                    'bid_amount' => $winner->bid->amount
                ]
            ]);
        }

        return $winner;
    }

    /**
     * Update auction with winner information.
     */
    private function updateAuctionWithWinner(Auction $auction, BidEvaluation $winnerEvaluation): void
    {
        $winnerBid = $winnerEvaluation->bid;
        
        $auction->update([
            'status' => 'completed',
            'ended_at' => now(),
            'winner_user_id' => $winnerBid->user_id,
            'winning_bid_id' => $winnerBid->id,
            'current_highest_bid' => $winnerBid->amount,
        ]);

        // Update winning bid status
        $winnerBid->update(['status' => 'accepted']);

        // Update other bids to rejected status
        Bid::where('auction_id', $auction->id)
            ->where('id', '!=', $winnerBid->id)
            ->update(['status' => 'rejected']);
    }

    /**
     * Send notifications to all participants about the winner.
     */
    private function sendWinnerNotifications(Auction $auction, BidEvaluation $winner, Collection $evaluations): void
    {
        try {
            // Notify the winner
            $this->notificationService->sendNotification([
                'type' => 'auction_won',
                'user_id' => $winner->bid->user_id,
                'data' => [
                    'auction_id' => $auction->id,
                    'auction_title' => $auction->title,
                    'winning_bid_amount' => $winner->bid->amount,
                    'composite_score' => $winner->composite_score,
                ]
            ]);

            // Notify the auction creator
            $this->notificationService->sendNotification([
                'type' => 'auction_completed',
                'user_id' => $auction->created_by,
                'data' => [
                    'auction_id' => $auction->id,
                    'auction_title' => $auction->title,
                    'winner_user_id' => $winner->bid->user_id,
                    'winning_bid_amount' => $winner->bid->amount,
                    'total_bids' => $evaluations->count(),
                ]
            ]);

            // Notify other bidders
            $loserBids = $evaluations->where('rank', '>', 1);
            foreach ($loserBids as $evaluation) {
                $this->notificationService->sendNotification([
                    'type' => 'auction_lost',
                    'user_id' => $evaluation->bid->user_id,
                    'data' => [
                        'auction_id' => $auction->id,
                        'auction_title' => $auction->title,
                        'your_rank' => $evaluation->rank,
                        'your_score' => $evaluation->composite_score,
                        'winner_score' => $winner->composite_score,
                    ]
                ]);
            }

        } catch (\Exception $e) {
            Log::warning('Failed to send winner notifications', [
                'auction_id' => $auction->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Fire winner selected event for downstream processing.
     */
    private function fireWinnerSelectedEvent(Auction $auction, BidEvaluation $winner, Collection $evaluations): void
    {
        try {
            $evaluationSummary = [
                'total_bids' => $evaluations->count(),
                'winner_rank' => $winner->rank,
                'winner_composite_score' => $winner->composite_score,
                'evaluation_criteria' => $winner->evaluation_criteria,
                'score_breakdown' => $winner->score_breakdown,
            ];

            WinnerSelectedEvent::dispatch(
                $auction->id,
                $winner->bid_id,
                $winner->bid->user_id,
                $winner->bid->amount,
                $winner->composite_score,
                $evaluationSummary
            );

            Log::info('Winner selected event fired', [
                'auction_id' => $auction->id,
                'winning_bid_id' => $winner->bid_id,
                'winner_user_id' => $winner->bid->user_id
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to fire winner selected event', [
                'auction_id' => $auction->id,
                'winning_bid_id' => $winner->bid_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get evaluation summary for an auction.
     */
    public function getEvaluationSummary(int $auctionId): array
    {
        $evaluations = BidEvaluation::where('auction_id', $auctionId)
            ->with(['bid.user'])
            ->byRank()
            ->get();

        return [
            'auction_id' => $auctionId,
            'total_evaluations' => $evaluations->count(),
            'winner' => $evaluations->where('rank', 1)->first()?->getSummary(),
            'all_evaluations' => $evaluations->map->getSummary(),
            'evaluation_completed_at' => $evaluations->first()?->evaluated_at,
        ];
    }

    /**
     * Re-evaluate auction with different criteria weights.
     */
    public function reEvaluateAuction(Auction $auction, array $newWeights): WinnerSelectionResult
    {
        // Delete existing evaluations
        BidEvaluation::where('auction_id', $auction->id)->delete();

        // Reset auction status
        $auction->update([
            'status' => 'active',
            'winner_user_id' => null,
            'winning_bid_id' => null,
        ]);

        // Re-run winner selection with new weights
        return $this->selectWinner($auction);
    }
}

/**
 * Winner Selection Result DTO
 */
class WinnerSelectionResult
{
    public function __construct(
        public bool $success,
        public string $message,
        public ?BidEvaluation $winner,
        public Collection $evaluations
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'winner' => $this->winner?->getSummary(),
            'evaluations_count' => $this->evaluations->count(),
            'evaluations' => $this->evaluations->map->getSummary(),
        ];
    }
}
