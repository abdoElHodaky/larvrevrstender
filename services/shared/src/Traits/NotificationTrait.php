<?php

namespace Shared\Traits;

/**
 * Generic Notification Trait
 * 
 * Provides a generic interface for all services to send notifications
 * by calling the notification service via RPC procedures.
 * 
 * @package Shared\Traits
 */
trait NotificationTrait
{
    /**
     * Default service context for notifications
     */
    protected ?string $notificationService = null;
    
    /**
     * Default language for notifications
     */
    protected string $notificationLanguage = 'en';
    
    /**
     * Set notification service context
     *
     * @param string $service
     * @return self
     */
    public function setNotificationService(string $service): self
    {
        $this->notificationService = $service;
        return $this;
    }
    
    /**
     * Set notification language
     *
     * @param string $language
     * @return self
     */
    public function setNotificationLanguage(string $language): self
    {
        $this->notificationLanguage = $language;
        return $this;
    }
    
    /**
     * Send simple notification
     *
     * @param string $channel Channel type (email, sms, whatsapp, telegram, push)
     * @param string $recipient Recipient
     * @param string $template Template name
     * @param array $data Template data
     * @param string|null $service Service context (overrides default)
     * @param string|null $language Language (overrides default)
     * @return bool Success status
     */
    public function sendNotification(
        string $channel,
        string $recipient,
        string $template,
        array $data = [],
        ?string $service = null,
        ?string $language = null
    ): bool {
        try {
            $result = $this->callNotificationService('sendNotification', [
                'channel' => $channel,
                'recipient' => $recipient,
                'template' => $template,
                'data' => $data,
                'service' => $service ?? $this->notificationService,
                'language' => $language ?? $this->notificationLanguage
            ]);
            
            return $result['success'] ?? false;
        } catch (\Exception $e) {
            $this->logNotificationError('sendNotification', $e->getMessage(), [
                'channel' => $channel,
                'recipient' => $recipient,
                'template' => $template
            ]);
            return false;
        }
    }
    
    /**
     * Send email notification
     *
     * @param array $config Email configuration
     * @return bool Success status
     */
    public function sendEmail(array $config): bool
    {
        try {
            $config = $this->applyDefaultContext($config);
            $result = $this->callNotificationService('sendEmail', $config);
            return $result['success'] ?? false;
        } catch (\Exception $e) {
            $this->logNotificationError('sendEmail', $e->getMessage(), $config);
            return false;
        }
    }
    
    /**
     * Send SMS notification
     *
     * @param array $config SMS configuration
     * @return bool Success status
     */
    public function sendSms(array $config): bool
    {
        try {
            $config = $this->applyDefaultContext($config);
            $result = $this->callNotificationService('sendSms', $config);
            return $result['success'] ?? false;
        } catch (\Exception $e) {
            $this->logNotificationError('sendSms', $e->getMessage(), $config);
            return false;
        }
    }
    
    /**
     * Send WhatsApp notification
     *
     * @param array $config WhatsApp configuration
     * @return bool Success status
     */
    public function sendWhatsApp(array $config): bool
    {
        try {
            $config = $this->applyDefaultContext($config);
            $result = $this->callNotificationService('sendWhatsApp', $config);
            return $result['success'] ?? false;
        } catch (\Exception $e) {
            $this->logNotificationError('sendWhatsApp', $e->getMessage(), $config);
            return false;
        }
    }
    
    /**
     * Send Telegram notification
     *
     * @param array $config Telegram configuration
     * @return bool Success status
     */
    public function sendTelegram(array $config): bool
    {
        try {
            $config = $this->applyDefaultContext($config);
            $result = $this->callNotificationService('sendTelegram', $config);
            return $result['success'] ?? false;
        } catch (\Exception $e) {
            $this->logNotificationError('sendTelegram', $e->getMessage(), $config);
            return false;
        }
    }
    
    /**
     * Send push notification
     *
     * @param array $config Push configuration
     * @return bool Success status
     */
    public function sendPushNotification(array $config): bool
    {
        try {
            $config = $this->applyDefaultContext($config);
            $result = $this->callNotificationService('sendPush', $config);
            return $result['success'] ?? false;
        } catch (\Exception $e) {
            $this->logNotificationError('sendPushNotification', $e->getMessage(), $config);
            return false;
        }
    }
    
    /**
     * Send multi-channel notification
     *
     * @param array $config Multi-channel configuration
     * @return bool Success status
     */
    public function sendMultiChannel(array $config): bool
    {
        try {
            $config = $this->applyDefaultContext($config);
            $result = $this->callNotificationService('sendMultiChannel', $config);
            return $result['success'] ?? false;
        } catch (\Exception $e) {
            $this->logNotificationError('sendMultiChannel', $e->getMessage(), $config);
            return false;
        }
    }
    
    /**
     * Send bulk notification
     *
     * @param array $config Bulk configuration
     * @return bool Success status
     */
    public function sendBulkNotification(array $config): bool
    {
        try {
            $config = $this->applyDefaultContext($config);
            $result = $this->callNotificationService('sendBulk', $config);
            return $result['success'] ?? false;
        } catch (\Exception $e) {
            $this->logNotificationError('sendBulkNotification', $e->getMessage(), $config);
            return false;
        }
    }
    
    /**
     * Schedule notification
     *
     * @param array $config Scheduled configuration
     * @return bool Success status
     */
    public function scheduleNotification(array $config): bool
    {
        try {
            $config = $this->applyDefaultContext($config);
            $result = $this->callNotificationService('scheduleNotification', $config);
            return $result['success'] ?? false;
        } catch (\Exception $e) {
            $this->logNotificationError('scheduleNotification', $e->getMessage(), $config);
            return false;
        }
    }
    
    /**
     * Cancel scheduled notification
     *
     * @param string $scheduleId Schedule ID
     * @return bool Success status
     */
    public function cancelNotification(string $scheduleId): bool
    {
        try {
            $result = $this->callNotificationService('cancelNotification', [
                'schedule_id' => $scheduleId
            ]);
            return $result['success'] ?? false;
        } catch (\Exception $e) {
            $this->logNotificationError('cancelNotification', $e->getMessage(), [
                'schedule_id' => $scheduleId
            ]);
            return false;
        }
    }
    
    /**
     * Get available notification channels
     *
     * @return array Available channels
     */
    public function getAvailableNotificationChannels(): array
    {
        try {
            $result = $this->callNotificationService('createFactory', [
                'service' => $this->notificationService,
                'language' => $this->notificationLanguage
            ]);
            return $result['available_channels'] ?? [];
        } catch (\Exception $e) {
            $this->logNotificationError('getAvailableNotificationChannels', $e->getMessage());
            return [];
        }
    }
    
    /**
     * Apply default service context and language to configuration
     *
     * @param array $config
     * @return array
     */
    private function applyDefaultContext(array $config): array
    {
        if (!isset($config['service']) && $this->notificationService) {
            $config['service'] = $this->notificationService;
        }
        
        if (!isset($config['language'])) {
            $config['language'] = $this->notificationLanguage;
        }
        
        return $config;
    }
    
    /**
     * Call notification service via RPC
     *
     * @param string $method RPC method name
     * @param array $params Parameters
     * @return array Response
     * @throws \Exception
     */
    private function callNotificationService(string $method, array $params = []): array
    {
        // This would be implemented based on your RPC mechanism
        // For example, using HTTP client, message queue, or direct service call
        
        // Example implementation (replace with your actual RPC mechanism):
        $rpcClient = $this->getRpcClient('notification-service');
        return $rpcClient->call("NotificationFactoryProcedure::{$method}", $params);
    }
    
    /**
     * Get RPC client for service communication
     * 
     * This method should be implemented based on your RPC infrastructure
     *
     * @param string $serviceName
     * @return mixed RPC client instance
     */
    abstract protected function getRpcClient(string $serviceName);
    
    /**
     * Log notification error
     *
     * @param string $method Method name
     * @param string $error Error message
     * @param array $context Context data
     * @return void
     */
    private function logNotificationError(string $method, string $error, array $context = []): void
    {
        // Log the error - implement based on your logging system
        error_log("Notification error in {$method}: {$error} " . json_encode($context));
        
        // You can also use a proper logging system here:
        // $this->logger->error("Notification error in {$method}", [
        //     'error' => $error,
        //     'context' => $context
        // ]);
    }
}
