<?php

namespace NotificationService\Builders;

use Shared\Services\TemplateManager;

/**
 * Multi-Channel Notification Builder
 * 
 * Builder for creating notifications that send across multiple channels
 * with fallback logic and channel-specific customization.
 * 
 * @package Shared\Services\Builders
 */
class MultiChannelNotificationBuilder extends BaseNotificationBuilder
{
    /**
     * Target channels
     */
    protected array $channels;
    
    /**
     * Channel-specific configurations
     */
    protected array $channelConfigs = [];
    
    /**
     * Fallback strategy
     */
    protected string $fallbackStrategy = 'all'; // all, first_success, priority_order
    
    /**
     * Channel priority order
     */
    protected array $priorityOrder = [];
    
    /**
     * Constructor
     *
     * @param TemplateManager $templateManager
     * @param array $channels
     */
    public function __construct(TemplateManager $templateManager, array $channels)
    {
        parent::__construct($templateManager, 'multi');
        $this->channels = $channels;
    }
    
    /**
     * Set fallback strategy
     *
     * @param string $strategy (all, first_success, priority_order)
     * @return static
     */
    public function withFallbackStrategy(string $strategy): static
    {
        $this->fallbackStrategy = $strategy;
        return $this;
    }
    
    /**
     * Set channel priority order
     *
     * @param array $order
     * @return static
     */
    public function withPriorityOrder(array $order): static
    {
        $this->priorityOrder = $order;
        return $this;
    }
    
    /**
     * Configure specific channel
     *
     * @param string $channel
     * @param array $config
     * @return static
     */
    public function configureChannel(string $channel, array $config): static
    {
        $this->channelConfigs[$channel] = $config;
        return $this;
    }
    
    /**
     * Send to all channels
     *
     * @return static
     */
    public function sendToAll(): static
    {
        return $this->withFallbackStrategy('all');
    }
    
    /**
     * Send until first success
     *
     * @return static
     */
    public function sendUntilSuccess(): static
    {
        return $this->withFallbackStrategy('first_success');
    }
    
    /**
     * Send by priority order
     *
     * @return static
     */
    public function sendByPriority(): static
    {
        return $this->withFallbackStrategy('priority_order');
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
        return $this->sendMultiChannel(
            $recipient,
            $this->channels,
            [
                'template' => $this->template,
                'data' => $data,
                'channel_configs' => $this->channelConfigs,
                'fallback_strategy' => $this->fallbackStrategy,
                'priority_order' => $this->priorityOrder,
                'service' => $this->service,
                'language' => $this->language,
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
        // Validate channels
        $validChannels = ['email', 'sms', 'whatsapp', 'telegram', 'push'];
        foreach ($this->channels as $channel) {
            if (!in_array($channel, $validChannels)) {
                $this->errors[] = "Invalid channel: {$channel}";
            }
        }
        
        // Validate fallback strategy
        if (!in_array($this->fallbackStrategy, ['all', 'first_success', 'priority_order'])) {
            $this->errors[] = "Invalid fallback strategy: {$this->fallbackStrategy}";
        }
        
        // Validate priority order if using priority strategy
        if ($this->fallbackStrategy === 'priority_order' && empty($this->priorityOrder)) {
            $this->errors[] = "Priority order is required when using priority_order strategy";
        }
    }
}
