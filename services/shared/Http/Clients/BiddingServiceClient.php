<?php

namespace Shared\Http\Clients;

class BiddingServiceClient extends BaseServiceClient
{
    public function placeBid(array $bidData): ?array
    {
        $response = $this->post('/bids', $bidData);
        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }

    public function getBid(string $bidId): ?array
    {
        $response = $this->get("/bids/{$bidId}");
        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }

    public function getUserBids(int $userId): ?array
    {
        $response = $this->get("/users/{$userId}/bids");
        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }
}
