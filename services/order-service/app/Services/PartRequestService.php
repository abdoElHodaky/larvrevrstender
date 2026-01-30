<?php

namespace App\Services;

use App\Models\PartRequest;
use App\Events\PartRequestCreated;
use App\Events\PartRequestUpdated;
use App\Events\PartRequestClosed;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PartRequestService
{
    /**
     * Get part request by ID.
     */
    public function getPartRequest(int $partRequestId): PartRequest
    {
        return PartRequest::with(['bids.merchant', 'winningBid', 'order'])->findOrFail($partRequestId);
    }

    /**
     * Get customer's part requests.
     */
    public function getCustomerPartRequests(int $customerId, array $filters = []): Collection
    {
        $query = PartRequest::with(['bids', 'winningBid'])
                           ->where('customer_id', $customerId)
                           ->orderBy('created_at', 'desc');
        
        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['category'])) {
            $query->byCategory($filters['category']);
        }
        
        if (isset($filters['urgency'])) {
            $query->byUrgency($filters['urgency']);
        }
        
        return $query->get();
    }

    /**
     * Create new part request.
     */
    public function createPartRequest(int $customerId, array $data): PartRequest
    {
        $data['customer_id'] = $customerId;
        
        // Set default expiration if not provided (7 days)
        if (!isset($data['expires_at'])) {
            $data['expires_at'] = now()->addDays(7);
        }
        
        $partRequest = PartRequest::create($data);
        
        event(new PartRequestCreated($partRequest));
        
        return $partRequest;
    }

    /**
     * Update part request.
     */
    public function updatePartRequest(int $partRequestId, array $data): PartRequest
    {
        $partRequest = $this->getPartRequest($partRequestId);
        
        // Don't allow updates if request has winning bid
        if ($partRequest->hasWinningBid()) {
            throw new \Exception('Cannot update part request with accepted bid');
        }
        
        $partRequest->update($data);
        
        event(new PartRequestUpdated($partRequest));
        
        return $partRequest->fresh();
    }

    /**
     * Activate part request.
     */
    public function activatePartRequest(int $partRequestId): PartRequest
    {
        $partRequest = $this->getPartRequest($partRequestId);
        
        if ($partRequest->status !== PartRequest::STATUS_DRAFT) {
            throw new \Exception('Only draft requests can be activated');
        }
        
        $partRequest->update(['status' => PartRequest::STATUS_ACTIVE]);
        
        event(new PartRequestUpdated($partRequest));
        
        return $partRequest->fresh();
    }

    /**
     * Close part request.
     */
    public function closePartRequest(int $partRequestId, string $reason = null): PartRequest
    {
        $partRequest = $this->getPartRequest($partRequestId);
        
        if (!in_array($partRequest->status, [PartRequest::STATUS_ACTIVE, PartRequest::STATUS_DRAFT])) {
            throw new \Exception('Request cannot be closed in current status');
        }
        
        $partRequest->close($reason);
        
        event(new PartRequestClosed($partRequest, $reason));
        
        return $partRequest->fresh();
    }

    /**
     * Cancel part request.
     */
    public function cancelPartRequest(int $partRequestId, string $reason = null): PartRequest
    {
        $partRequest = $this->getPartRequest($partRequestId);
        
        if ($partRequest->hasWinningBid()) {
            throw new \Exception('Cannot cancel request with accepted bid');
        }
        
        $partRequest->cancel($reason);
        
        // Withdraw all pending bids
        $partRequest->bids()->where('status', 'pending')->update([
            'status' => 'withdrawn',
            'rejection_reason' => 'Part request was cancelled'
        ]);
        
        event(new PartRequestClosed($partRequest, $reason));
        
        return $partRequest->fresh();
    }

    /**
     * Extend part request expiration.
     */
    public function extendExpiration(int $partRequestId, int $days): PartRequest
    {
        $partRequest = $this->getPartRequest($partRequestId);
        
        if ($partRequest->status !== PartRequest::STATUS_ACTIVE) {
            throw new \Exception('Only active requests can be extended');
        }
        
        $partRequest->extendExpiration($days);
        
        event(new PartRequestUpdated($partRequest));
        
        return $partRequest->fresh();
    }

    /**
     * Search part requests for merchants.
     */
    public function searchPartRequestsForMerchants(array $filters = []): Collection
    {
        $query = PartRequest::with(['customer', 'vehicle'])
                           ->active()
                           ->notExpired()
                           ->orderBy('created_at', 'desc');
        
        // Apply filters
        if (isset($filters['category'])) {
            $query->byCategory($filters['category']);
        }
        
        if (isset($filters['urgency'])) {
            $query->byUrgency($filters['urgency']);
        }
        
        if (isset($filters['budget_min'], $filters['budget_max'])) {
            $query->withinBudget($filters['budget_min'], $filters['budget_max']);
        }
        
        if (isset($filters['part_number'])) {
            $query->where('part_number', 'like', '%' . $filters['part_number'] . '%');
        }
        
        if (isset($filters['brand_preference'])) {
            $query->where('brand_preference', 'like', '%' . $filters['brand_preference'] . '%');
        }
        
        if (isset($filters['condition_preference'])) {
            $query->where('condition_preference', $filters['condition_preference']);
        }
        
        // Location-based filtering (simplified)
        if (isset($filters['location'])) {
            $query->whereJsonContains('location_preferences', $filters['location']);
        }
        
        return $query->get();
    }

    /**
     * Get part request statistics.
     */
    public function getPartRequestStats(int $partRequestId): array
    {
        $partRequest = $this->getPartRequest($partRequestId);
        
        return [
            'total_bids' => $partRequest->bid_count,
            'active_bids' => $partRequest->activeBids()->count(),
            'lowest_bid' => $partRequest->lowest_bid,
            'highest_bid' => $partRequest->highest_bid,
            'average_bid' => $partRequest->activeBids()->avg('amount'),
            'time_remaining' => $partRequest->time_remaining,
            'is_expired' => $partRequest->isExpired(),
            'has_winning_bid' => $partRequest->hasWinningBid(),
            'can_receive_bids' => $partRequest->canReceiveBids(),
            'views_count' => $partRequest->metadata['views_count'] ?? 0,
        ];
    }

    /**
     * Get trending part categories.
     */
    public function getTrendingCategories(int $days = 30): array
    {
        return PartRequest::where('created_at', '>=', now()->subDays($days))
                         ->selectRaw('part_category, COUNT(*) as request_count')
                         ->groupBy('part_category')
                         ->orderBy('request_count', 'desc')
                         ->limit(10)
                         ->pluck('request_count', 'part_category')
                         ->toArray();
    }

    /**
     * Get customer request statistics.
     */
    public function getCustomerRequestStats(int $customerId): array
    {
        $requests = PartRequest::where('customer_id', $customerId);
        
        return [
            'total_requests' => $requests->count(),
            'active_requests' => $requests->where('status', PartRequest::STATUS_ACTIVE)->count(),
            'completed_requests' => $requests->where('status', PartRequest::STATUS_CLOSED)->count(),
            'cancelled_requests' => $requests->where('status', PartRequest::STATUS_CANCELLED)->count(),
            'average_bids_per_request' => $requests->avg('bid_count'),
            'total_spent' => $requests->whereHas('order')->with('order')->get()->sum('order.total_amount'),
        ];
    }

    /**
     * Mark expired requests.
     */
    public function markExpiredRequests(): int
    {
        $expiredCount = PartRequest::active()
                                  ->where('expires_at', '<', now())
                                  ->update(['status' => PartRequest::STATUS_CLOSED]);
        
        // Also mark expired bids
        \App\Models\Bid::pending()
                       ->where('expires_at', '<', now())
                       ->update(['status' => \App\Models\Bid::STATUS_EXPIRED]);
        
        return $expiredCount;
    }

    /**
     * Get part request recommendations for customer.
     */
    public function getRecommendationsForCustomer(int $customerId): Collection
    {
        // Get customer's previous requests to understand preferences
        $customerRequests = PartRequest::where('customer_id', $customerId)
                                     ->orderBy('created_at', 'desc')
                                     ->limit(10)
                                     ->get();
        
        if ($customerRequests->isEmpty()) {
            return collect();
        }
        
        // Get most common categories and brands
        $commonCategories = $customerRequests->pluck('part_category')->unique()->take(3);
        $commonBrands = $customerRequests->pluck('brand_preference')->filter()->unique()->take(3);
        
        // Find similar active requests
        $query = PartRequest::active()
                           ->notExpired()
                           ->where('customer_id', '!=', $customerId)
                           ->whereIn('part_category', $commonCategories);
        
        if ($commonBrands->isNotEmpty()) {
            $query->whereIn('brand_preference', $commonBrands);
        }
        
        return $query->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get();
    }

    /**
     * Duplicate part request.
     */
    public function duplicatePartRequest(int $partRequestId, int $customerId): PartRequest
    {
        $originalRequest = $this->getPartRequest($partRequestId);
        
        // Verify customer owns the original request
        if ($originalRequest->customer_id !== $customerId) {
            throw new \Exception('Cannot duplicate another customer\'s request');
        }
        
        $duplicateData = $originalRequest->toArray();
        
        // Remove fields that shouldn't be duplicated
        unset($duplicateData['id'], $duplicateData['created_at'], $duplicateData['updated_at']);
        unset($duplicateData['bid_count'], $duplicateData['lowest_bid'], $duplicateData['highest_bid']);
        unset($duplicateData['status'], $duplicateData['expires_at']);
        
        // Set new values
        $duplicateData['status'] = PartRequest::STATUS_DRAFT;
        $duplicateData['title'] = $duplicateData['title'] . ' (Copy)';
        $duplicateData['expires_at'] = now()->addDays(7);
        
        return $this->createPartRequest($customerId, $duplicateData);
    }

    /**
     * Validate part request data.
     */
    public function validatePartRequestData(array $data): array
    {
        $errors = [];
        
        // Required fields
        if (empty($data['title'])) {
            $errors[] = 'Title is required';
        }
        
        if (empty($data['description'])) {
            $errors[] = 'Description is required';
        }
        
        if (empty($data['part_category'])) {
            $errors[] = 'Part category is required';
        }
        
        // Budget validation
        if (isset($data['budget_min'], $data['budget_max'])) {
            if ($data['budget_min'] > $data['budget_max']) {
                $errors[] = 'Minimum budget cannot be greater than maximum budget';
            }
        }
        
        // Expiration validation
        if (isset($data['expires_at'])) {
            $expiresAt = is_string($data['expires_at']) ? \Carbon\Carbon::parse($data['expires_at']) : $data['expires_at'];
            if ($expiresAt->isPast()) {
                $errors[] = 'Expiration date cannot be in the past';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Delete part request.
     */
    public function deletePartRequest(int $partRequestId, int $customerId): bool
    {
        $partRequest = $this->getPartRequest($partRequestId);
        
        // Verify customer owns the request
        if ($partRequest->customer_id !== $customerId) {
            throw new \Exception('Cannot delete another customer\'s request');
        }
        
        // Don't allow deletion if there are accepted bids or orders
        if ($partRequest->hasWinningBid() || $partRequest->order) {
            throw new \Exception('Cannot delete request with accepted bids or orders');
        }
        
        return $partRequest->delete();
    }
}

