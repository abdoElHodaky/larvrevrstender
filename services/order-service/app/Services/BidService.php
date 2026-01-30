<?php

namespace App\Services;

use App\Models\Bid;
use App\Models\PartRequest;
use App\Events\BidCreated;
use App\Events\BidAccepted;
use App\Events\BidRejected;
use App\Events\BidWithdrawn;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class BidService
{
    private OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Get bid by ID.
     */
    public function getBid(int $bidId): Bid
    {
        return Bid::with(['partRequest', 'merchant'])->findOrFail($bidId);
    }

    /**
     * Get merchant's bids.
     */
    public function getMerchantBids(int $merchantId, array $filters = []): Collection
    {
        $query = Bid::with(['partRequest.customer'])
                   ->byMerchant($merchantId)
                   ->orderBy('created_at', 'desc');
        
        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['part_request_id'])) {
            $query->where('part_request_id', $filters['part_request_id']);
        }
        
        if (isset($filters['amount_min'], $filters['amount_max'])) {
            $query->withinAmountRange($filters['amount_min'], $filters['amount_max']);
        }
        
        return $query->get();
    }

    /**
     * Get bids for part request.
     */
    public function getPartRequestBids(int $partRequestId): Collection
    {
        return Bid::with(['merchant'])
                 ->where('part_request_id', $partRequestId)
                 ->orderBy('amount', 'asc')
                 ->get();
    }

    /**
     * Create new bid.
     */
    public function createBid(int $merchantId, int $partRequestId, array $bidData): Bid
    {
        $partRequest = PartRequest::findOrFail($partRequestId);
        
        // Validate part request can receive bids
        if (!$partRequest->canReceiveBids()) {
            throw new \Exception('Part request cannot receive bids');
        }
        
        // Check if merchant already has a bid for this request
        $existingBid = Bid::where('part_request_id', $partRequestId)
                         ->where('merchant_id', $merchantId)
                         ->first();
        
        if ($existingBid) {
            throw new \Exception('Merchant already has a bid for this request');
        }
        
        // Validate bid data
        $validation = $this->validateBidData($bidData, $partRequest);
        if (!$validation['valid']) {
            throw new \Exception('Bid validation failed: ' . implode(', ', $validation['errors']));
        }
        
        $bidData['merchant_id'] = $merchantId;
        $bidData['part_request_id'] = $partRequestId;
        
        // Set default expiration if not provided (3 days)
        if (!isset($bidData['expires_at'])) {
            $bidData['expires_at'] = now()->addDays(3);
        }
        
        $bid = Bid::create($bidData);
        
        // Update part request bid statistics
        $partRequest->updateBidStats();
        
        event(new BidCreated($bid));
        
        return $bid;
    }

    /**
     * Update bid.
     */
    public function updateBid(int $bidId, int $merchantId, array $bidData): Bid
    {
        $bid = $this->getBid($bidId);
        
        // Verify merchant owns the bid
        if ($bid->merchant_id !== $merchantId) {
            throw new \Exception('Cannot update another merchant\'s bid');
        }
        
        // Only allow updates for pending bids
        if (!$bid->isPending()) {
            throw new \Exception('Only pending bids can be updated');
        }
        
        // Validate bid data
        $validation = $this->validateBidData($bidData, $bid->partRequest);
        if (!$validation['valid']) {
            throw new \Exception('Bid validation failed: ' . implode(', ', $validation['errors']));
        }
        
        $bid->update($bidData);
        
        // Update part request bid statistics
        $bid->partRequest->updateBidStats();
        
        return $bid->fresh();
    }

    /**
     * Accept bid.
     */
    public function acceptBid(int $bidId, int $customerId): array
    {
        $bid = $this->getBid($bidId);
        
        // Verify customer owns the part request
        if ($bid->partRequest->customer_id !== $customerId) {
            throw new \Exception('Cannot accept bid for another customer\'s request');
        }
        
        if (!$bid->canBeAccepted()) {
            throw new \Exception('Bid cannot be accepted');
        }
        
        // Accept the bid (this also rejects other bids and closes the request)
        $bid->accept();
        
        // Create order from the accepted bid
        $order = $this->orderService->createOrderFromBid($bid);
        
        event(new BidAccepted($bid, $order));
        
        return [
            'bid' => $bid->fresh(),
            'order' => $order
        ];
    }

    /**
     * Reject bid.
     */
    public function rejectBid(int $bidId, int $customerId, string $reason = null): Bid
    {
        $bid = $this->getBid($bidId);
        
        // Verify customer owns the part request
        if ($bid->partRequest->customer_id !== $customerId) {
            throw new \Exception('Cannot reject bid for another customer\'s request');
        }
        
        if ($bid->status !== Bid::STATUS_PENDING) {
            throw new \Exception('Only pending bids can be rejected');
        }
        
        $bid->reject($reason);
        
        // Update part request bid statistics
        $bid->partRequest->updateBidStats();
        
        event(new BidRejected($bid, $reason));
        
        return $bid->fresh();
    }

    /**
     * Withdraw bid.
     */
    public function withdrawBid(int $bidId, int $merchantId, string $reason = null): Bid
    {
        $bid = $this->getBid($bidId);
        
        // Verify merchant owns the bid
        if ($bid->merchant_id !== $merchantId) {
            throw new \Exception('Cannot withdraw another merchant\'s bid');
        }
        
        $bid->withdraw($reason);
        
        event(new BidWithdrawn($bid, $reason));
        
        return $bid->fresh();
    }

    /**
     * Get bid statistics.
     */
    public function getBidStats(int $bidId): array
    {
        $bid = $this->getBid($bidId);
        $partRequest = $bid->partRequest;
        
        return [
            'competitive_ranking' => $bid->competitive_ranking,
            'is_lowest_bid' => $bid->amount == $partRequest->lowest_bid,
            'is_highest_bid' => $bid->amount == $partRequest->highest_bid,
            'within_budget' => $bid->isWithinBudget(),
            'total_cost' => $bid->total_cost,
            'delivery_time_display' => $bid->delivery_time_display,
            'warranty_display' => $bid->warranty_display,
            'time_remaining' => $bid->expires_at ? $bid->expires_at->diffForHumans() : null,
            'is_expired' => $bid->isExpired(),
        ];
    }

    /**
     * Get merchant bid statistics.
     */
    public function getMerchantBidStats(int $merchantId): array
    {
        $bids = Bid::byMerchant($merchantId);
        
        return [
            'total_bids' => $bids->count(),
            'pending_bids' => $bids->where('status', Bid::STATUS_PENDING)->count(),
            'accepted_bids' => $bids->where('status', Bid::STATUS_ACCEPTED)->count(),
            'rejected_bids' => $bids->where('status', Bid::STATUS_REJECTED)->count(),
            'withdrawn_bids' => $bids->where('status', Bid::STATUS_WITHDRAWN)->count(),
            'acceptance_rate' => $this->calculateAcceptanceRate($merchantId),
            'average_bid_amount' => $bids->avg('amount'),
            'total_won_value' => $bids->where('status', Bid::STATUS_ACCEPTED)->sum('amount'),
        ];
    }

    /**
     * Calculate merchant acceptance rate.
     */
    private function calculateAcceptanceRate(int $merchantId): float
    {
        $totalBids = Bid::byMerchant($merchantId)
                       ->whereIn('status', [Bid::STATUS_ACCEPTED, Bid::STATUS_REJECTED])
                       ->count();
        
        if ($totalBids === 0) {
            return 0.0;
        }
        
        $acceptedBids = Bid::byMerchant($merchantId)
                          ->where('status', Bid::STATUS_ACCEPTED)
                          ->count();
        
        return round(($acceptedBids / $totalBids) * 100, 2);
    }

    /**
     * Get competitive analysis for bid.
     */
    public function getCompetitiveAnalysis(int $bidId): array
    {
        $bid = $this->getBid($bidId);
        $partRequest = $bid->partRequest;
        $allBids = $partRequest->activeBids()->orderBy('amount', 'asc')->get();
        
        if ($allBids->count() <= 1) {
            return [
                'total_competitors' => 0,
                'position' => 1,
                'price_difference_to_lowest' => 0,
                'price_difference_to_highest' => 0,
                'recommendations' => []
            ];
        }
        
        $position = $allBids->search(function ($item) use ($bid) {
            return $item->id === $bid->id;
        }) + 1;
        
        $lowestBid = $allBids->first();
        $highestBid = $allBids->last();
        
        $recommendations = [];
        
        if ($position > 1) {
            $priceDifference = $bid->amount - $lowestBid->amount;
            $recommendations[] = "Consider reducing price by " . number_format($priceDifference, 2) . " SAR to match the lowest bid";
        }
        
        if ($bid->delivery_days > $allBids->min('delivery_days')) {
            $recommendations[] = "Consider improving delivery time to be more competitive";
        }
        
        if ($bid->warranty_months < $allBids->max('warranty_months')) {
            $recommendations[] = "Consider extending warranty period to be more competitive";
        }
        
        return [
            'total_competitors' => $allBids->count() - 1,
            'position' => $position,
            'price_difference_to_lowest' => $bid->amount - $lowestBid->amount,
            'price_difference_to_highest' => $highestBid->amount - $bid->amount,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Validate bid data.
     */
    public function validateBidData(array $bidData, PartRequest $partRequest): array
    {
        $errors = [];
        
        // Required fields
        if (empty($bidData['amount']) || $bidData['amount'] <= 0) {
            $errors[] = 'Valid bid amount is required';
        }
        
        // Budget validation
        if (isset($bidData['amount'])) {
            $totalCost = $bidData['amount'] + ($bidData['delivery_cost'] ?? 0);
            
            if ($partRequest->budget_max && $totalCost > $partRequest->budget_max) {
                $errors[] = 'Total cost exceeds maximum budget of ' . number_format($partRequest->budget_max, 2) . ' SAR';
            }
            
            if ($partRequest->budget_min && $totalCost < $partRequest->budget_min) {
                $errors[] = 'Total cost is below minimum budget of ' . number_format($partRequest->budget_min, 2) . ' SAR';
            }
        }
        
        // Delivery validation
        if (isset($bidData['delivery_days']) && $bidData['delivery_days'] < 0) {
            $errors[] = 'Delivery days cannot be negative';
        }
        
        if (isset($bidData['delivery_cost']) && $bidData['delivery_cost'] < 0) {
            $errors[] = 'Delivery cost cannot be negative';
        }
        
        // Warranty validation
        if (isset($bidData['warranty_months']) && $bidData['warranty_months'] < 0) {
            $errors[] = 'Warranty months cannot be negative';
        }
        
        // Expiration validation
        if (isset($bidData['expires_at'])) {
            $expiresAt = is_string($bidData['expires_at']) ? \Carbon\Carbon::parse($bidData['expires_at']) : $bidData['expires_at'];
            if ($expiresAt->isPast()) {
                $errors[] = 'Bid expiration cannot be in the past';
            }
            
            if ($partRequest->expires_at && $expiresAt->isAfter($partRequest->expires_at)) {
                $errors[] = 'Bid cannot expire after the part request expires';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get bid recommendations for merchant.
     */
    public function getBidRecommendations(int $merchantId, int $partRequestId): array
    {
        $partRequest = PartRequest::findOrFail($partRequestId);
        $existingBids = $partRequest->activeBids()->orderBy('amount', 'asc')->get();
        
        $recommendations = [
            'suggested_amount' => null,
            'competitive_advantages' => [],
            'market_insights' => [],
            'risk_factors' => []
        ];
        
        // Suggest competitive pricing
        if ($existingBids->isNotEmpty()) {
            $lowestBid = $existingBids->first()->amount;
            $averageBid = $existingBids->avg('amount');
            
            $recommendations['suggested_amount'] = $lowestBid - 1; // Slightly lower than lowest
            $recommendations['market_insights'][] = "Current lowest bid: " . number_format($lowestBid, 2) . " SAR";
            $recommendations['market_insights'][] = "Average bid: " . number_format($averageBid, 2) . " SAR";
        } else {
            // No existing bids, suggest based on budget
            if ($partRequest->budget_max) {
                $recommendations['suggested_amount'] = $partRequest->budget_max * 0.9; // 90% of max budget
            } elseif ($partRequest->budget_min) {
                $recommendations['suggested_amount'] = $partRequest->budget_min * 1.1; // 110% of min budget
            }
        }
        
        // Analyze competitive advantages
        if ($existingBids->isNotEmpty()) {
            $fastestDelivery = $existingBids->min('delivery_days');
            $longestWarranty = $existingBids->max('warranty_months');
            
            $recommendations['competitive_advantages'][] = "Offer delivery faster than " . $fastestDelivery . " days";
            $recommendations['competitive_advantages'][] = "Provide warranty longer than " . $longestWarranty . " months";
        }
        
        // Risk factors
        if ($partRequest->urgency === PartRequest::URGENCY_URGENT) {
            $recommendations['risk_factors'][] = "High urgency request - customer may accept quickly";
        }
        
        if ($partRequest->expires_at && $partRequest->expires_at->diffInHours() < 24) {
            $recommendations['risk_factors'][] = "Request expires soon - limited time to compete";
        }
        
        return $recommendations;
    }

    /**
     * Mark expired bids.
     */
    public function markExpiredBids(): int
    {
        return Bid::pending()
                 ->where('expires_at', '<', now())
                 ->update(['status' => Bid::STATUS_EXPIRED]);
    }

    /**
     * Delete bid.
     */
    public function deleteBid(int $bidId, int $merchantId): bool
    {
        $bid = $this->getBid($bidId);
        
        // Verify merchant owns the bid
        if ($bid->merchant_id !== $merchantId) {
            throw new \Exception('Cannot delete another merchant\'s bid');
        }
        
        // Only allow deletion of pending bids
        if ($bid->status !== Bid::STATUS_PENDING) {
            throw new \Exception('Only pending bids can be deleted');
        }
        
        $deleted = $bid->delete();
        
        // Update part request bid statistics
        $bid->partRequest->updateBidStats();
        
        return $deleted;
    }
}

