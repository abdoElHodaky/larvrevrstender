<?php

namespace Shared\Services\Builders;

use Shared\Services\TemplateManager;

/**
 * Bulk Notification Builder
 * 
 * Builder for creating bulk notifications with batch processing,
 * rate limiting, and progress tracking.
 * 
 * @package Shared\Services\Builders
 */
class BulkNotificationBuilder extends BaseNotificationBuilder
{
    /**
     * Batch size
     */
    protected int $batchSize = 100;
    
    /**
     * Rate limit (notifications per minute)
     */
    protected ?int $rateLimit = null;
    
    /**
     * Progress callback
     */
    protected $progressCallback = null;
    
    /**
     * Error callback
     */
    protected $errorCallback = null;
    
    /**
     * Constructor
     *
     * @param TemplateManager $templateManager
     * @param string $channel
     */
    public function __construct(TemplateManager $templateManager, string $channel)
    {
        parent::__construct($templateManager, $channel);
    }
    
    /**
     * Set batch size
     *
     * @param int $size
     * @return static
     */
    public function withBatchSize(int $size): static
    {
        $this->batchSize = $size;
        return $this;
    }
    
    /**
     * Set rate limit
     *
     * @param int $limit Notifications per minute
     * @return static
     */
    public function withRateLimit(int $limit): static
    {
        $this->rateLimit = $limit;
        return $this;
    }
    
    /**
     * Set progress callback
     *
     * @param callable $callback
     * @return static
     */
    public function onProgress(callable $callback): static
    {
        $this->progressCallback = $callback;
        return $this;
    }
    
    /**
     * Set error callback
     *
     * @param callable $callback
     * @return static
     */
    public function onError(callable $callback): static
    {
        $this->errorCallback = $callback;
        return $this;
    }
    
    /**
     * Send notification to specific recipient
     *
     * @param string $recipient
     * @param array $data
     * @return bool
     */
    protected function sendToRecipient(string $recipient, array $data): bool
    {
        // This method is overridden by send() for bulk processing
        return true;
    }
    
    /**
     * Send bulk notifications
     *
     * @return bool
     */
    public function send(): bool
    {
        if (!$this->validate()) {
            throw new \Exception('Validation failed: ' . implode(', ', $this->errors));
        }
        
        return $this->sendBulkNotification(
            $this->recipients,
            [
                'channel' => $this->channel,
                'template' => $this->template,
                'data' => $this->data,
                'service' => $this->service,
                'language' => $this->language,
                'batch_size' => $this->batchSize,
                'rate_limit' => $this->rateLimit,
                'progress_callback' => $this->progressCallback,
                'error_callback' => $this->errorCallback,
                'priority' => $this->priority,
                'tracking' => $this->tracking,
                'metadata' => $this->metadata
            ]
        );
    }
    
    /**
     * Channel-specific validation
     *
     * @return void
     */
    protected function validateChannel(): void
    {
        // Validate batch size
        if ($this->batchSize < 1 || $this->batchSize > 1000) {
            $this->errors[] = "Batch size must be between 1 and 1000";
        }
        
        // Validate rate limit
        if ($this->rateLimit !== null && ($this->rateLimit < 1 || $this->rateLimit > 10000)) {
            $this->errors[] = "Rate limit must be between 1 and 10000 per minute";
        }
        
        // Validate minimum recipients for bulk
        if (count($this->recipients) < 2) {
            $this->errors[] = "Bulk notifications require at least 2 recipients";
        }
    }
}
