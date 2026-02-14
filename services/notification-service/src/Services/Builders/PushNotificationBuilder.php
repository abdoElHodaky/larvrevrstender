<?php

namespace NotificationService\Services\Builders;

use Shared\Services\TemplateManager;

/**
 * Push Notification Builder
 * 
 * Builder for creating push notifications with push-specific features
 * like title, icon, actions, and platform targeting.
 * 
 * @package Shared\Services\Builders
 */
class PushNotificationBuilder extends BaseNotificationBuilder
{
    /**
     * Notification title
     */
    protected ?string $title = null;
    
    /**
     * Notification icon
     */
    protected ?string $icon = null;
    
    /**
     * Notification image
     */
    protected ?string $image = null;
    
    /**
     * Click action URL
     */
    protected ?string $clickAction = null;
    
    /**
     * Notification actions
     */
    protected array $actions = [];
    
    /**
     * Target platforms
     */
    protected array $platforms = ['android', 'ios', 'web'];
    
    /**
     * Time to live (seconds)
     */
    protected ?int $ttl = null;
    
    /**
     * Constructor
     *
     * @param TemplateManager $templateManager
     */
    public function __construct(TemplateManager $templateManager)
    {
        parent::__construct($templateManager, 'push');
    }
    
    /**
     * Set notification title
     *
     * @param string $title
     * @return static
     */
    public function withTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }
    
    /**
     * Set notification icon
     *
     * @param string $icon
     * @return static
     */
    public function withIcon(string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }
    
    /**
     * Set notification image
     *
     * @param string $image
     * @return static
     */
    public function withImage(string $image): static
    {
        $this->image = $image;
        return $this;
    }
    
    /**
     * Set click action URL
     *
     * @param string $url
     * @return static
     */
    public function withClickAction(string $url): static
    {
        $this->clickAction = $url;
        return $this;
    }
    
    /**
     * Add action button
     *
     * @param string $title
     * @param string $action
     * @return static
     */
    public function withAction(string $title, string $action): static
    {
        $this->actions[] = [
            'title' => $title,
            'action' => $action
        ];
        return $this;
    }
    
    /**
     * Set target platforms
     *
     * @param array $platforms
     * @return static
     */
    public function forPlatforms(array $platforms): static
    {
        $this->platforms = $platforms;
        return $this;
    }
    
    /**
     * Target Android only
     *
     * @return static
     */
    public function forAndroid(): static
    {
        return $this->forPlatforms(['android']);
    }
    
    /**
     * Target iOS only
     *
     * @return static
     */
    public function forIos(): static
    {
        return $this->forPlatforms(['ios']);
    }
    
    /**
     * Target web only
     *
     * @return static
     */
    public function forWeb(): static
    {
        return $this->forPlatforms(['web']);
    }
    
    /**
     * Set time to live
     *
     * @param int $seconds
     * @return static
     */
    public function withTtl(int $seconds): static
    {
        $this->ttl = $seconds;
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
        $content = $this->buildContent($data);
        $title = $this->buildTitle($data);
        
        return $this->sendPushNotification(
            $recipient,
            $title,
            $content,
            [
                'template' => $this->template,
                'data' => $data,
                'icon' => $this->icon,
                'image' => $this->image,
                'click_action' => $this->clickAction,
                'actions' => $this->actions,
                'platforms' => $this->platforms,
                'ttl' => $this->ttl,
                'priority' => $this->priority,
                'tracking' => $this->tracking,
                'metadata' => $this->metadata
            ]
        );
    }
    
    /**
     * Build notification title
     *
     * @param array $data
     * @return string
     */
    protected function buildTitle(array $data): string
    {
        if (!$this->title) {
            // Try to extract title from template data
            if (isset($data['title'])) {
                return (string) $data['title'];
            }
            
            // Generate default title
            return 'Notification';
        }
        
        // Process title as template
        $title = $this->title;
        foreach ($data as $key => $value) {
            $title = str_replace('{{' . $key . '}}', (string) $value, $title);
        }
        
        return $title;
    }
    
    /**
     * Channel-specific validation
     *
     * @return void
     */
    protected function validateChannel(): void
    {
        // Validate platforms
        $validPlatforms = ['android', 'ios', 'web'];
        foreach ($this->platforms as $platform) {
            if (!in_array($platform, $validPlatforms)) {
                $this->errors[] = "Invalid platform: {$platform}";
            }
        }
        
        // Validate URLs
        if ($this->clickAction && !filter_var($this->clickAction, FILTER_VALIDATE_URL)) {
            $this->errors[] = "Invalid click action URL: {$this->clickAction}";
        }
        
        // Validate TTL
        if ($this->ttl !== null && ($this->ttl < 0 || $this->ttl > 2419200)) {
            $this->errors[] = "TTL must be between 0 and 2419200 seconds (28 days)";
        }
        
        // Validate action limits
        if (count($this->actions) > 3) {
            $this->errors[] = "Maximum 3 actions allowed";
        }
    }
}
