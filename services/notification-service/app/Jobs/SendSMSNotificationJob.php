<?php

namespace App\Jobs;

use Shared\Jobs\BaseQueueJob;
use Illuminate\Support\Facades\Log;

/**
 * Send SMS Notification Job with Laravel Fuse Circuit Breaker Protection
 * 
 * This job handles SMS notification delivery with built-in circuit breaker
 * protection via Laravel Fuse integration. Protects against SMS service
 * outages and prevents queue worker starvation.
 */
class SendSMSNotificationJob extends BaseQueueJob
{
    /**
     * SMS recipient phone number
     */
    public string $phoneNumber;

    /**
     * SMS message content
     */
    public string $message;

    /**
     * SMS sender ID (optional)
     */
    public ?string $senderId;

    /**
     * Additional SMS metadata
     */
    public array $metadata;

    /**
     * Create a new job instance
     *
     * @param string $phoneNumber
     * @param string $message
     * @param string|null $senderId
     * @param array $metadata
     */
    public function __construct(
        string $phoneNumber,
        string $message,
        ?string $senderId = null,
        array $metadata = []
    ) {
        // Initialize with 'sms' service for circuit breaker configuration
        parent::__construct('sms');
        
        $this->phoneNumber = $phoneNumber;
        $this->message = $message;
        $this->senderId = $senderId;
        $this->metadata = $metadata;
    }

    /**
     * Execute the job with circuit breaker protection
     *
     * @return void
     */
    public function handle(): void
    {
        Log::info('Processing SMS notification job', [
            'phone_number' => $this->phoneNumber,
            'message_length' => strlen($this->message),
            'service' => $this->getServiceName(),
            'sender_id' => $this->senderId
        ]);

        try {
            // Send the SMS
            $this->sendSMS();
            
            Log::info('SMS notification sent successfully', [
                'phone_number' => $this->phoneNumber,
                'service' => $this->getServiceName()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send SMS notification', [
                'phone_number' => $this->phoneNumber,
                'service' => $this->getServiceName(),
                'error' => $e->getMessage()
            ]);

            // Re-throw to trigger circuit breaker failure handling
            throw $e;
        }
    }

    /**
     * Send the SMS notification
     *
     * @return void
     * @throws \Exception
     */
    private function sendSMS(): void
    {
        // Example implementation - replace with actual SMS service
        // This could be Twilio, AWS SNS, Nexmo, etc.
        
        $smsData = [
            'to' => $this->phoneNumber,
            'body' => $this->message,
            'from' => $this->senderId ?? config('sms.default_sender_id'),
            'metadata' => $this->metadata
        ];

        // Example: Twilio SMS sending
        // $twilio = new Client(config('twilio.sid'), config('twilio.token'));
        // $twilio->messages->create($this->phoneNumber, $smsData);
        
        // For now, simulate successful SMS sending
        Log::debug('SMS sent via provider', $smsData);
    }

    /**
     * Handle job failure
     *
     * @param \Throwable $exception
     * @return void
     */
    public function onFailure(\Throwable $exception): void
    {
        Log::critical('SMS notification job failed permanently', [
            'phone_number' => $this->phoneNumber,
            'service' => $this->getServiceName(),
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Could trigger fallback notification mechanism here
        // e.g., Email notification, Push notification, etc.
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array
     */
    public function tags(): array
    {
        return array_merge(parent::tags(), [
            'phone:' . substr($this->phoneNumber, -4), // Last 4 digits for privacy
            'sender:' . ($this->senderId ?? 'default')
        ]);
    }
}
