<?php

namespace App\Activities;

use Shared\Procedures\CrossServiceProcedure;
use Workflow\ActivityInterface;
use Illuminate\Support\Facades\Log;

/**
 * Initiate Payment Activity
 * 
 * Coordinates with payment-service to initiate the payment process
 * for the auction winner. This triggers the payment saga workflow.
 */
class InitiatePaymentActivity implements ActivityInterface
{
    private CrossServiceProcedure $crossService;

    public function __construct()
    {
        $this->crossService = new CrossServiceProcedure();
    }

    /**
     * Execute the payment initiation activity
     */
    public function execute(array $input): array
    {
        Log::info('Starting payment initiation', ['input' => $input]);

        try {
            $auctionId = $input['auction_id'];
            $winner = $input['winner'];

            // Skip payment initiation if there's no winner
            if (!$winner) {
                Log::info('No winner found, skipping payment initiation', [
                    'auction_id' => $auctionId
                ]);

                return [
                    'success' => true,
                    'auction_id' => $auctionId,
                    'payment_initiated' => false,
                    'reason' => 'no_winner',
                    'message' => 'No payment initiation needed - no winner'
                ];
            }

            // Prepare payment initiation data
            $paymentData = [
                'auction_id' => $auctionId,
                'payer_user_id' => $winner['user_id'],
                'amount' => $winner['winning_amount'],
                'currency' => 'USD', // Default currency
                'payment_type' => 'auction_payment',
                'description' => "Payment for auction #{$auctionId} - winning bid",
                'metadata' => [
                    'auction_id' => $auctionId,
                    'bid_id' => $winner['bid_id'],
                    'winning_amount' => $winner['winning_amount'],
                    'bid_time' => $winner['bid_time']
                ]
            ];

            // Call payment service to initiate payment
            $response = $this->crossService->callService('payment-service', 'initiate_payment', $paymentData, []);

            if (!$response || !$response['success']) {
                throw new \Exception('Payment service initiation failed: ' . ($response['message'] ?? 'Unknown error'));
            }

            Log::info('Payment initiation successful', [
                'auction_id' => $auctionId,
                'winner' => $winner,
                'payment_response' => $response
            ]);

            return [
                'success' => true,
                'auction_id' => $auctionId,
                'winner' => $winner,
                'payment_initiated' => true,
                'payment_id' => $response['payment_id'] ?? null,
                'payment_response' => $response,
                'message' => 'Payment initiation successful'
            ];

        } catch (\Exception $e) {
            Log::error('Payment initiation activity failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $input
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to initiate payment'
            ];
        }
    }
}

