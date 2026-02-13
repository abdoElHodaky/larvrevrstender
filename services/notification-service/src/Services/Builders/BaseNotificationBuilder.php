<?php

namespace NotificationService\Services\Builders;

use Shared\Services\TemplateManager;
use Shared\Procedures\Micro\NotificationProcedure;
use Exception;

/**
 * Base Notification Builder
 * 
 * Abstract base class for all notification builders providing common
 * functionality and fluent interface methods.
 * 
 * @package Shared\Services\Builders
 */
abstract class BaseNotificationBuilder
{
    use NotificationProcedure;
    
    /**
     * Template manager instance
     */
    protected TemplateManager $templateManager;
    
    /**
     * Notification channel
     */
    protected string $channel;
    
    /**
     * Template name
     */
    protected ?string $template = null;
    
    /**
     * Template data
     */
    protected array $data = [];
    
    /**
     * Recipients
     */
    protected array $recipients = [];
    
    /**
     * Service context
     */
    protected ?string $service = null;
    
    /**
     * Language code
     */
    protected string $language = 'en';
    
    /**
     * Priority level
     */
    protected string $priority = 'normal';
    
    /**
     * Tracking enabled
     */
    protected bool $tracking = false;
    
    /**
     * Retry configuration
     */
    protected array $retryConfig = [
        'enabled' => false,
        'max_attempts' => 3,
        'delay' => 60
    ];
    
    /**
     * Metadata
     */
    protected array $metadata = [];
    
    /**
     * Validation errors
     */
    protected array $errors = [];
    
    /**
     * Constructor
     *
     * @param TemplateManager $templateManager
     * @param string $channel
     */
    public function __construct(TemplateManager $templateManager, string $channel)
    {
        $this->templateManager = $templateManager;
        $this->channel = $channel;
    }
    
    /**
     * Set template name
     *
     * @param string $template
     * @return static
     */
    public function withTemplate(string $template): static
    {
        $this->template = $template;
        return $this;
    }
    
    /**
     * Set template data
     *
     * @param array $data
     * @return static
     */
    public function withData(array $data): static
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }
    
    /**
     * Add single data item
     *
     * @param string $key
     * @param mixed $value
     * @return static
     */
    public function with(string $key, $value): static
    {
        $this->data[$key] = $value;
        return $this;
    }
    
    /**
     * Add recipient
     *
     * @param string $recipient
     * @param array $data Additional recipient-specific data
     * @return static
     */
    public function to(string $recipient, array $data = []): static
    {
        $this->recipients[] = [
            'recipient' => $recipient,
            'data' => $data
        ];
        return $this;
    }
    
    /**
     * Add multiple recipients
     *
     * @param array $recipients
     * @return static
     */
    public function toMany(array $recipients): static
    {
        foreach ($recipients as $recipient) {
            if (is_string($recipient)) {
                $this->to($recipient);
            } elseif (is_array($recipient) && isset($recipient['recipient'])) {
                $this->to($recipient['recipient'], $recipient['data'] ?? []);
            }
        }
        return $this;
    }
    
    /**
     * Set service context
     *
     * @param string|null $service
     * @return static
     */
    public function setService(?string $service): static
    {
        $this->service = $service;
        return $this;
    }
    
    /**
     * Set language
     *
     * @param string $language
     * @return static
     */
    public function setLanguage(string $language): static
    {
        $this->language = $language;
        return $this;
    }
    
    /**
     * Set priority
     *
     * @param string $priority (low, normal, high, urgent)
     * @return static
     */
    public function withPriority(string $priority): static
    {
        $this->priority = $priority;
        return $this;
    }
    
    /**
     * Enable tracking
     *
     * @param bool $enabled
     * @return static
     */
    public function withTracking(bool $enabled = true): static
    {
        $this->tracking = $enabled;
        return $this;
    }
    
    /**
     * Configure retry settings
     *
     * @param int $maxAttempts
     * @param int $delay Delay in seconds
     * @return static
     */
    public function withRetry(int $maxAttempts = 3, int $delay = 60): static
    {
        $this->retryConfig = [
            'enabled' => true,
            'max_attempts' => $maxAttempts,
            'delay' => $delay
        ];
        return $this;
    }
    
    /**
     * Add metadata
     *
     * @param array $metadata
     * @return static
     */
    public function withMetadata(array $metadata): static
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }
    
    /**
     * Add single metadata item
     *
     * @param string $key
     * @param mixed $value
     * @return static
     */
    public function withMeta(string $key, $value): static
    {
        $this->metadata[$key] = $value;
        return $this;
    }
    
    /**
     * Conditional method execution
     *
     * @param mixed $condition
     * @param callable $callback
     * @return static
     */
    public function when($condition, callable $callback): static
    {
        if ($condition) {
            $callback($this);
        }
        return $this;
    }
    
    /**
     * Validate builder configuration
     *
     * @return bool
     */
    public function validate(): bool
    {
        $this->errors = [];
        
        // Check required fields
        if (empty($this->recipients)) {
            $this->errors[] = 'At least one recipient is required';
        }
        
        if (!$this->template) {
            $this->errors[] = 'Template is required';
        }
        
        // Validate priority
        if (!in_array($this->priority, ['low', 'normal', 'high', 'urgent'])) {
            $this->errors[] = 'Invalid priority level';
        }
        
        // Channel-specific validation
        $this->validateChannel();
        
        return empty($this->errors);
    }
    
    /**
     * Get validation errors
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Build and send notification
     *
     * @return bool
     * @throws Exception
     */
    public function send(): bool
    {
        if (!$this->validate()) {
            throw new Exception('Validation failed: ' . implode(', ', $this->errors));
        }
        
        $success = true;
        
        foreach ($this->recipients as $recipientData) {
            $recipient = $recipientData['recipient'];
            $recipientSpecificData = array_merge($this->data, $recipientData['data']);
            
            try {
                $result = $this->sendToRecipient($recipient, $recipientSpecificData);
                if (!$result) {
                    $success = false;
                }
            } catch (Exception $e) {
                $this->log('error', 'Notification send failed', [
                    'channel' => $this->channel,
                    'recipient' => $recipient,
                    'template' => $this->template,
                    'error' => $e->getMessage()
                ]);
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Build notification without sending
     *
     * @return array
     * @throws Exception
     */
    public function build(): array
    {
        if (!$this->validate()) {
            throw new Exception('Validation failed: ' . implode(', ', $this->errors));
        }
        
        $notifications = [];
        
        foreach ($this->recipients as $recipientData) {
            $recipient = $recipientData['recipient'];
            $recipientSpecificData = array_merge($this->data, $recipientData['data']);
            
            $notifications[] = [
                'channel' => $this->channel,
                'recipient' => $recipient,
                'template' => $this->template,
                'data' => $recipientSpecificData,
                'service' => $this->service,
                'language' => $this->language,
                'priority' => $this->priority,
                'tracking' => $this->tracking,
                'retry_config' => $this->retryConfig,
                'metadata' => $this->metadata,
                'content' => $this->buildContent($recipientSpecificData)
            ];
        }
        
        return $notifications;
    }
    
    /**
     * Build notification content using template
     *
     * @param array $data
     * @return string
     */
    protected function buildContent(array $data): string
    {
        if (!$this->template) {
            return '';
        }
        
        return $this->templateManager->processTemplate(
            $this->template,
            $this->channel,
            $data,
            $this->language,
            $this->service
        );
    }
    
    /**
     * Send notification to specific recipient
     *
     * @param string $recipient
     * @param array $data
     * @return bool
     */
    abstract protected function sendToRecipient(string $recipient, array $data): bool;
    
    /**
     * Channel-specific validation
     *
     * @return void
     */
    abstract protected function validateChannel(): void;
    
    /**
     * Get channel name
     *
     * @return string
     */
    public function getChannel(): string
    {
        return $this->channel;
    }
    
    /**
     * Get template name
     *
     * @return string|null
     */
    public function getTemplate(): ?string
    {
        return $this->template;
    }
    
    /**
     * Get template data
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
    
    /**
     * Get recipients
     *
     * @return array
     */
    public function getRecipients(): array
    {
        return $this->recipients;
    }
    
    /**
     * Get service context
     *
     * @return string|null
     */
    public function getService(): ?string
    {
        return $this->service;
    }
    
    /**
     * Get language
     *
     * @return string
     */
    public function getLanguage(): string
    {
        return $this->language;
    }
}
