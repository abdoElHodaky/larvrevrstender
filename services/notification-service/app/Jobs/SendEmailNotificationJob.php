<?php

namespace App\Jobs;

use Shared\Jobs\BaseQueueJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Send Email Notification Job with Laravel Fuse Circuit Breaker Protection
 * 
 * This job handles email notification delivery with built-in circuit breaker
 * protection via Laravel Fuse integration. Protects against email service
 * outages and prevents queue worker starvation.
 */
class SendEmailNotificationJob extends BaseQueueJob
{
    /**
     * Email recipient
     */
    public string $recipient;

    /**
     * Email subject
     */
    public string $subject;

    /**
     * Email content
     */
    public string $content;

    /**
     * Email template (optional)
     */
    public ?string $template;

    /**
     * Additional email data
     */
    public array $data;

    /**
     * Create a new job instance
     *
     * @param string $recipient
     * @param string $subject
     * @param string $content
     * @param string|null $template
     * @param array $data
     */
    public function __construct(
        string $recipient,
        string $subject,
        string $content,
        ?string $template = null,
        array $data = []
    ) {
        // Initialize with 'email' service for circuit breaker configuration
        parent::__construct('email');
        
        $this->recipient = $recipient;
        $this->subject = $subject;
        $this->content = $content;
        $this->template = $template;
        $this->data = $data;
    }

    /**
     * Execute the job with circuit breaker protection
     *
     * @return void
     */
    public function handle(): void
    {
        Log::info('Processing email notification job', [
            'recipient' => $this->recipient,
            'subject' => $this->subject,
            'service' => $this->getServiceName(),
            'template' => $this->template
        ]);

        try {
            // Simulate email sending (replace with actual email service)
            $this->sendEmail();
            
            Log::info('Email notification sent successfully', [
                'recipient' => $this->recipient,
                'subject' => $this->subject,
                'service' => $this->getServiceName()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send email notification', [
                'recipient' => $this->recipient,
                'subject' => $this->subject,
                'service' => $this->getServiceName(),
                'error' => $e->getMessage()
            ]);

            // Re-throw to trigger circuit breaker failure handling
            throw $e;
        }
    }

    /**
     * Send the email notification
     *
     * @return void
     * @throws \Exception
     */
    private function sendEmail(): void
    {
        // Example implementation - replace with actual email service
        // This could be Mailgun, SendGrid, AWS SES, etc.
        
        if ($this->template) {
            // Send templated email
            Mail::send($this->template, $this->data, function ($message) {
                $message->to($this->recipient)
                        ->subject($this->subject);
            });
        } else {
            // Send plain email
            Mail::raw($this->content, function ($message) {
                $message->to($this->recipient)
                        ->subject($this->subject);
            });
        }
    }

    /**
     * Handle job failure
     *
     * @param \Throwable $exception
     * @return void
     */
    public function onFailure(\Throwable $exception): void
    {
        Log::critical('Email notification job failed permanently', [
            'recipient' => $this->recipient,
            'subject' => $this->subject,
            'service' => $this->getServiceName(),
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Could trigger fallback notification mechanism here
        // e.g., SMS notification, Slack alert, etc.
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array
     */
    public function tags(): array
    {
        return array_merge(parent::tags(), [
            'recipient:' . $this->recipient,
            'template:' . ($this->template ?? 'plain')
        ]);
    }
}
