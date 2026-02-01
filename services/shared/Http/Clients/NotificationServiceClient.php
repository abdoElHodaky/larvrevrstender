<?php

namespace Shared\Http\Clients;

class NotificationServiceClient extends BaseServiceClient
{
    public function sendNotification(array $notificationData): ?array
    {
        $response = $this->post('/notifications', $notificationData);
        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }

    public function sendBulkNotification(array $criteria, array $notificationData): ?array
    {
        $response = $this->post('/notifications/bulk', [
            'criteria' => $criteria,
            'notification' => $notificationData,
        ]);
        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }

    public function getNotificationStatus(string $notificationId): ?array
    {
        $response = $this->get("/notifications/{$notificationId}");
        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }
}
