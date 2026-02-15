<?php

namespace App\Activities;

use Shared\Procedures\CrossServiceProcedure;
use Workflow\ActivityInterface;
use Illuminate\Support\Facades\Log;

/**
 * Cancel Payment Activity (Compensation)
 * 
 * Compensation activity that cancels payment initiation when the auction ending saga fails
 * after payment was initiated. This prevents orphaned payment processes.
 */
class CancelPaymentActivity implements ActivityInterface
{
    private CrossServiceProcedure $crossService;

    public function __construct()
    {
        $this->crossService = new CrossServiceProcedure();
    }

    /**
     * Execute the payment cancellation compensation activity
     */
    public function execute(array $input): array
    {
        Log::info('Starting payment cancellation compensation', ['input' => $input]);

        try {
            $auctionId = $input['auction_id'];
            $winner = $input['winner'];

            // Prepare payment cancellation data
            $cancellationData = [
                'auction_id' => $auctionId,
                'user_id' => $winner['user_id'],
                'amount' => $winner['winning_amount'],
                'reason' => 'auction_ending_saga_failed',
                'metadata' => [
                    'original_auction_id' => $auctionId,
                    'original_bid_id' => $winner['bid_id'],
                    'compensation_reason' => 'auction_ending_saga_failure'
                ]
            ];

            // Call payment service to cancel payment
            $response = $this->crossService->callService('payment-service', 'cancel_payment', $cancellationData, []);

            if (!$response || !$response['success']) {
                // Log warning but don't fail the compensation
                // Payment cancellation is best-effort during compensation
                Log::warning('Payment service cancellation returned non-success response', [
                    'auction_id' => $auctionId,
                    'winner' => $winner,
                    'response' => $response
                ]);
            }

            Log::info('Payment cancellation compensation completed', [
                'auction_id' => $auctionId,
                'winner' => $winner,
                'response' => $response
            ]);

            return [
                'success' => true,
                'auction_id' => $auctionId,
                'winner' => $winner,
                'cancellation_response' => $response,
                'message' => 'Payment cancellation compensation completed'
            ];

        } catch (\Exception $e) {
            // Log error but don't fail the compensation
            // Payment cancellation is best-effort during compensation
            Log::error('Payment cancellation compensation activity encountered error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $input
            ]);

            return [
                'success' => true, // Return success to not block other compensations
                'error' => $e->getMessage(),
                'message' => 'Payment cancellation encountered error but compensation continues'
            ];
        }
    }
}

