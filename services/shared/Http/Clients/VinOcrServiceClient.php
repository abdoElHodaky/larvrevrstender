<?php

namespace Shared\Http\Clients;

class VinOcrServiceClient extends BaseServiceClient
{
    public function processOcr(array $ocrData): ?array
    {
        $response = $this->post('/ocr/process', $ocrData);
        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }

    public function getOcrResult(string $requestId): ?array
    {
        $response = $this->get("/ocr/results/{$requestId}");
        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }

    public function getUserUsage(int $userId): ?array
    {
        $response = $this->get("/users/{$userId}/usage");
        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }
}
