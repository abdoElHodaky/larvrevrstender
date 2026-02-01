<?php

namespace Shared\Http\Clients;

class AnalyticsServiceClient extends BaseServiceClient
{
    public function collectMetric(array $metricData): bool
    {
        $response = $this->post('/metrics', $metricData);
        return $this->isSuccessful($response);
    }

    public function getReport(string $reportType, array $filters = []): ?array
    {
        $response = $this->post("/reports/{$reportType}", $filters);
        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }

    public function getServiceHealth(): ?array
    {
        $response = $this->get('/health/services');
        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }
}
