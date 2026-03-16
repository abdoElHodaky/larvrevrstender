<?php

declare(strict_types=1);

namespace Shared\Clients;

use Shared\RPC\AbstractRpcClient;
use Shared\RPC\Enums\ServiceType;
use Shared\RPC\ValueObjects\RpcRequest;
use Shared\RPC\ValueObjects\RpcResponse;

/**
 * Auction Service RPC Client - PHP 8.3 & Laravel 12 Implementation
 * 
 * Modern, type-safe RPC client for auction management service
 * with comprehensive auction lifecycle and product management.
 */
class AuctionServiceClient extends AbstractRpcClient
{
    public function __construct($httpClient, string $environment = 'local')
    {
        parent::__construct($httpClient, ServiceType::AUCTION, $environment);
    }

    /**
     * Create new auction
     */
    public function createAuction(array $auctionData): RpcResponse
    {
        $request = RpcRequest::post('/auctions', $auctionData);
        return $this->call($request);
    }

    /**
     * Get auction by ID
     */
    public function getAuction(int $auctionId): RpcResponse
    {
        $request = RpcRequest::get("/auctions/{$auctionId}");
        return $this->call($request);
    }

    /**
     * Update auction details
     */
    public function updateAuction(int $auctionId, array $auctionData): RpcResponse
    {
        $request = RpcRequest::put("/auctions/{$auctionId}", $auctionData);
        return $this->call($request);
    }

    /**
     * Delete auction
     */
    public function deleteAuction(int $auctionId): RpcResponse
    {
        $request = RpcRequest::delete("/auctions/{$auctionId}");
        return $this->call($request);
    }

    /**
     * Search auctions with filters
     */
    public function searchAuctions(array $filters = [], int $page = 1, int $limit = 20): RpcResponse
    {
        $request = RpcRequest::get('/auctions/search', [
            'filters' => $filters,
            'page' => $page,
            'limit' => $limit,
        ]);
        return $this->call($request);
    }

    /**
     * Get active auctions
     */
    public function getActiveAuctions(int $page = 1, int $limit = 20): RpcResponse
    {
        $request = RpcRequest::get('/auctions/active', [
            'page' => $page,
            'limit' => $limit,
        ]);
        return $this->call($request);
    }

    /**
     * Start auction
     */
    public function startAuction(int $auctionId): RpcResponse
    {
        $request = RpcRequest::post("/auctions/{$auctionId}/start");
        return $this->call($request);
    }

    /**
     * End auction
     */
    public function endAuction(int $auctionId): RpcResponse
    {
        $request = RpcRequest::post("/auctions/{$auctionId}/end");
        return $this->call($request);
    }

    /**
     * Cancel auction
     */
    public function cancelAuction(int $auctionId, string $reason): RpcResponse
    {
        $request = RpcRequest::post("/auctions/{$auctionId}/cancel", [
            'reason' => $reason,
        ]);
        return $this->call($request);
    }

    /**
     * Get auction statistics
     */
    public function getAuctionStats(int $auctionId): RpcResponse
    {
        $request = RpcRequest::get("/auctions/{$auctionId}/stats");
        return $this->call($request);
    }

    /**
     * Add product image to auction
     */
    public function addProductImage(int $auctionId, array $imageData): RpcResponse
    {
        $request = RpcRequest::post("/auctions/{$auctionId}/images", $imageData);
        return $this->call($request);
    }

    /**
     * Remove product image from auction
     */
    public function removeProductImage(int $auctionId, int $imageId): RpcResponse
    {
        $request = RpcRequest::delete("/auctions/{$auctionId}/images/{$imageId}");
        return $this->call($request);
    }

    /**
     * Get auction images
     */
    public function getAuctionImages(int $auctionId): RpcResponse
    {
        $request = RpcRequest::get("/auctions/{$auctionId}/images");
        return $this->call($request);
    }

    /**
     * Get auctions by seller
     */
    public function getAuctionsBySeller(int $sellerId, int $page = 1, int $limit = 20): RpcResponse
    {
        $request = RpcRequest::get('/auctions/by-seller', [
            'seller_id' => $sellerId,
            'page' => $page,
            'limit' => $limit,
        ]);
        return $this->call($request);
    }

    /**
     * Get auction categories
     */
    public function getAuctionCategories(): RpcResponse
    {
        $request = RpcRequest::get('/auctions/categories');
        return $this->call($request);
    }

    /**
     * Extend auction duration
     */
    public function extendAuction(int $auctionId, int $extensionMinutes): RpcResponse
    {
        $request = RpcRequest::post("/auctions/{$auctionId}/extend", [
            'extension_minutes' => $extensionMinutes,
        ]);
        return $this->call($request);
    }
}
