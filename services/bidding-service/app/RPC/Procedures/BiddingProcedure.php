<?php

namespace App\RPC\Procedures;

use App\RPC\BaseProcedure;
use App\Http\Controllers\BiddingController;
use App\Http\Controllers\AuctionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BiddingProcedure extends BaseProcedure
{
    /**
     * Place a bid on an auction
     * 
     * @param array $params
     * @return array
     */
    public function placeBid(array $params): array
    {
        $startTime = microtime(true);
        
        try {
            $this->validate($params, [
                'auction_id' => 'required|integer',
                'user_id' => 'required|integer',
                'amount' => 'required|numeric|min:0',
                'currency' => 'nullable|string|max:3',
                'notes' => 'nullable|string|max:1000',
            ]);

            $controller = new BiddingController();
            $request = new Request($params);
            
            $result = $controller->placeBid($request);
            
            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            
            return $result->getData(true);
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Get bid details
     * 
     * @param array $params
     * @return array
     */
    public function getBid(array $params): array
    {
        $startTime = microtime(true);
        
        try {
            $this->validate($params, [
                'bid_id' => 'required|integer',
            ]);

            $controller = new BiddingController();
            $result = $controller->getBid($params['bid_id']);
            
            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            
            return $result->getData(true);
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Get all bids for a user
     * 
     * @param array $params
     * @return array
     */
    public function getUserBids(array $params): array
    {
        $startTime = microtime(true);
        
        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
                'status' => 'nullable|string|in:pending,accepted,rejected,withdrawn',
                'limit' => 'nullable|integer|min:1|max:100',
                'offset' => 'nullable|integer|min:0',
            ]);

            $controller = new BiddingController();
            $result = $controller->getUserBids($params['user_id']);
            
            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            
            return $result->getData(true);
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Get all bids for an auction
     * 
     * @param array $params
     * @return array
     */
    public function getAuctionBids(array $params): array
    {
        $startTime = microtime(true);
        
        try {
            $this->validate($params, [
                'auction_id' => 'required|integer',
                'status' => 'nullable|string|in:pending,accepted,rejected,withdrawn',
                'limit' => 'nullable|integer|min:1|max:100',
                'offset' => 'nullable|integer|min:0',
            ]);

            $controller = new BiddingController();
            $result = $controller->getAuctionBids($params['auction_id']);
            
            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            
            return $result->getData(true);
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Update bid status
     * 
     * @param array $params
     * @return array
     */
    public function updateBidStatus(array $params): array
    {
        $startTime = microtime(true);
        
        try {
            $this->validate($params, [
                'bid_id' => 'required|integer',
                'status' => 'required|string|in:pending,accepted,rejected,withdrawn',
                'reason' => 'nullable|string|max:500',
            ]);

            $controller = new BiddingController();
            $request = new Request([
                'status' => $params['status'],
                'reason' => $params['reason'] ?? null,
            ]);
            
            $result = $controller->updateBidStatus($request, $params['bid_id']);
            
            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            
            return $result->getData(true);
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Create a new auction
     * 
     * @param array $params
     * @return array
     */
    public function createAuction(array $params): array
    {
        $startTime = microtime(true);
        
        try {
            $this->validate($params, [
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'starting_price' => 'required|numeric|min:0',
                'reserve_price' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|max:3',
                'start_time' => 'required|date',
                'end_time' => 'required|date|after:start_time',
                'category_id' => 'nullable|integer',
                'user_id' => 'required|integer',
            ]);

            $controller = new AuctionController();
            $request = new Request($params);
            
            $result = $controller->store($request);
            
            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            
            return $result->getData(true);
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Get auction details
     * 
     * @param array $params
     * @return array
     */
    public function getAuction(array $params): array
    {
        $startTime = microtime(true);
        
        try {
            $this->validate($params, [
                'auction_id' => 'required|integer',
            ]);

            $controller = new AuctionController();
            $result = $controller->show($params['auction_id']);
            
            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            
            return $result->getData(true);
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Update auction details
     * 
     * @param array $params
     * @return array
     */
    public function updateAuction(array $params): array
    {
        $startTime = microtime(true);
        
        try {
            $this->validate($params, [
                'auction_id' => 'required|integer',
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'starting_price' => 'nullable|numeric|min:0',
                'reserve_price' => 'nullable|numeric|min:0',
                'end_time' => 'nullable|date',
            ]);

            $controller = new AuctionController();
            $request = new Request($params);
            
            $result = $controller->update($request, $params['auction_id']);
            
            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            
            return $result->getData(true);
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Close an auction
     * 
     * @param array $params
     * @return array
     */
    public function closeAuction(array $params): array
    {
        $startTime = microtime(true);
        
        try {
            $this->validate($params, [
                'auction_id' => 'required|integer',
                'reason' => 'nullable|string|max:500',
            ]);

            $controller = new AuctionController();
            $request = new Request(['reason' => $params['reason'] ?? null]);
            
            $result = $controller->close($request, $params['auction_id']);
            
            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            
            return $result->getData(true);
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Get auction status
     * 
     * @param array $params
     * @return array
     */
    public function getAuctionStatus(array $params): array
    {
        $startTime = microtime(true);
        
        try {
            $this->validate($params, [
                'auction_id' => 'required|integer',
            ]);

            $controller = new AuctionController();
            $auction = $controller->show($params['auction_id']);
            $auctionData = $auction->getData(true);
            
            $this->logPerformance(__METHOD__, $params, $auction, $startTime);
            
            return [
                'auction_id' => $auctionData['id'],
                'status' => $auctionData['status'],
                'current_highest_bid' => $auctionData['current_highest_bid'] ?? null,
                'total_bids' => $auctionData['total_bids'] ?? 0,
                'time_remaining' => $auctionData['time_remaining'] ?? null,
                'is_active' => $auctionData['is_active'] ?? false,
            ];
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Get bid history for an auction
     * 
     * @param array $params
     * @return array
     */
    public function getBidHistory(array $params): array
    {
        $startTime = microtime(true);
        
        try {
            $this->validate($params, [
                'auction_id' => 'required|integer',
                'limit' => 'nullable|integer|min:1|max:100',
                'offset' => 'nullable|integer|min:0',
            ]);

            $controller = new AuctionController();
            $result = $controller->getBids($params['auction_id']);
            
            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            
            return $result->getData(true);
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Cancel/withdraw a bid
     * 
     * @param array $params
     * @return array
     */
    public function cancelBid(array $params): array
    {
        $startTime = microtime(true);
        
        try {
            $this->validate($params, [
                'bid_id' => 'required|integer',
                'reason' => 'nullable|string|max:500',
            ]);

            $controller = new BiddingController();
            $request = new Request(['reason' => $params['reason'] ?? null]);
            
            $result = $controller->cancel($request, $params['bid_id']);
            
            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            
            return [
                'success' => $result->getStatusCode() === 200,
                'message' => 'Bid cancelled successfully',
            ];
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }
}
