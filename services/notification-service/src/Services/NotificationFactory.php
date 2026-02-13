<?php

namespace NotificationService\Services;

use NotificationService\Services\Builders\EmailNotificationBuilder;
use NotificationService\Services\Builders\SmsNotificationBuilder;
use NotificationService\Services\Builders\WhatsAppNotificationBuilder;
use NotificationService\Services\Builders\TelegramNotificationBuilder;
use NotificationService\Services\Builders\PushNotificationBuilder;
use NotificationService\Services\Builders\MultiChannelNotificationBuilder;
use NotificationService\Services\Builders\BulkNotificationBuilder;
use NotificationService\Services\Builders\ScheduledNotificationBuilder;
use Exception;

/**
 * Notification Factory
 * 
 * Factory class for creating different types of notification builders
 * with fluent interface support and integration with TemplateManager.
 * 
 * @package Shared\Services
 */
class NotificationFactory
{
    /**
     * Template manager instance
     */
    private TemplateManager $templateManager;
    
    /**
     * Default service context
     */
    private ?string $defaultService = null;
    
    /**
     * Default language
     */
    private string $defaultLanguage = 'en';
    
    /**
     * Constructor
     *
     * @param TemplateManager|null $templateManager
     */
    public function __construct(?TemplateManager $templateManager = null)
    {
        $this->templateManager = $templateManager ?? new TemplateManager();
    }
    
    /**
     * Create email notification builder
     *
     * @param string|null $template Template name
     * @return EmailNotificationBuilder
     */
    public function email(?string $template = null): EmailNotificationBuilder
    {
        return new EmailNotificationBuilder($this->templateManager)
            ->setService($this->defaultService)
            ->setLanguage($this->defaultLanguage)
            ->when($template, fn($builder) => $builder->withTemplate($template));
    }
    
    /**
     * Create SMS notification builder
     *
     * @param string|null $template Template name
     * @return SmsNotificationBuilder
     */
    public function sms(?string $template = null): SmsNotificationBuilder
    {
        return new SmsNotificationBuilder($this->templateManager)
            ->setService($this->defaultService)
            ->setLanguage($this->defaultLanguage)
            ->when($template, fn($builder) => $builder->withTemplate($template));
    }
    
    /**
     * Create WhatsApp notification builder
     *
     * @param string|null $template Template name
     * @return WhatsAppNotificationBuilder
     */
    public function whatsapp(?string $template = null): WhatsAppNotificationBuilder
    {
        return new WhatsAppNotificationBuilder($this->templateManager)
            ->setService($this->defaultService)
            ->setLanguage($this->defaultLanguage)
            ->when($template, fn($builder) => $builder->withTemplate($template));
    }
    
    /**
     * Create Telegram notification builder
     *
     * @param string|null $template Template name
     * @return TelegramNotificationBuilder
     */
    public function telegram(?string $template = null): TelegramNotificationBuilder
    {
        return new TelegramNotificationBuilder($this->templateManager)
            ->setService($this->defaultService)
            ->setLanguage($this->defaultLanguage)
            ->when($template, fn($builder) => $builder->withTemplate($template));
    }
    
    /**
     * Create push notification builder
     *
     * @param string|null $template Template name
     * @return PushNotificationBuilder
     */
    public function push(?string $template = null): PushNotificationBuilder
    {
        return new PushNotificationBuilder($this->templateManager)
            ->setService($this->defaultService)
            ->setLanguage($this->defaultLanguage)
            ->when($template, fn($builder) => $builder->withTemplate($template));
    }
    
    /**
     * Create multi-channel notification builder
     *
     * @param array $channels Channels to use (e.g., ['email', 'sms', 'whatsapp'])
     * @param string|null $template Template name
     * @return MultiChannelNotificationBuilder
     */
    public function multiChannel(array $channels, ?string $template = null): MultiChannelNotificationBuilder
    {
        return new MultiChannelNotificationBuilder($this->templateManager, $channels)
            ->setService($this->defaultService)
            ->setLanguage($this->defaultLanguage)
            ->when($template, fn($builder) => $builder->withTemplate($template));
    }
    
    /**
     * Create bulk notification builder
     *
     * @param string $channel Channel type
     * @param string|null $template Template name
     * @return BulkNotificationBuilder
     */
    public function bulk(string $channel, ?string $template = null): BulkNotificationBuilder
    {
        return new BulkNotificationBuilder($this->templateManager, $channel)
            ->setService($this->defaultService)
            ->setLanguage($this->defaultLanguage)
            ->when($template, fn($builder) => $builder->withTemplate($template));
    }
    
    /**
     * Create scheduled notification builder
     *
     * @param string $channel Channel type
     * @param string|null $template Template name
     * @return ScheduledNotificationBuilder
     */
    public function scheduled(string $channel, ?string $template = null): ScheduledNotificationBuilder
    {
        return new ScheduledNotificationBuilder($this->templateManager, $channel)
            ->setService($this->defaultService)
            ->setLanguage($this->defaultLanguage)
            ->when($template, fn($builder) => $builder->withTemplate($template));
    }
    
    /**
     * Set default service context for all builders
     *
     * @param string $service Service name
     * @return self
     */
    public function forService(string $service): self
    {
        $this->defaultService = $service;
        return $this;
    }
    
    /**
     * Set default language for all builders
     *
     * @param string $language Language code
     * @return self
     */
    public function inLanguage(string $language): self
    {
        $this->defaultLanguage = $language;
        return $this;
    }
    
    /**
     * Create notification builder by channel name
     *
     * @param string $channel Channel name
     * @param string|null $template Template name
     * @return mixed
     * @throws Exception
     */
    public function channel(string $channel, ?string $template = null)
    {
        return match ($channel) {
            'email' => $this->email($template),
            'sms' => $this->sms($template),
            'whatsapp' => $this->whatsapp($template),
            'telegram' => $this->telegram($template),
            'push' => $this->push($template),
            default => throw new Exception("Unsupported notification channel: {$channel}")
        };
    }
    
    /**
     * Create a quick notification for common use cases
     *
     * @param string $channel Channel type
     * @param string $recipient Recipient (email, phone, etc.)
     * @param string $template Template name
     * @param array $data Template data
     * @param string|null $service Service context
     * @param string|null $language Language code
     * @return bool
     */
    public function quick(
        string $channel,
        string $recipient,
        string $template,
        array $data = [],
        ?string $service = null,
        ?string $language = null
    ): bool {
        try {
            return $this->channel($channel, $template)
                ->to($recipient)
                ->withData($data)
                ->when($service, fn($builder) => $builder->setService($service))
                ->when($language, fn($builder) => $builder->setLanguage($language))
                ->send();
        } catch (Exception $e) {
            // Log error and return false for quick notifications
            error_log("Quick notification failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get template manager instance
     *
     * @return TemplateManager
     */
    public function getTemplateManager(): TemplateManager
    {
        return $this->templateManager;
    }
    
    /**
     * Get available notification channels
     *
     * @return array
     */
    public function getAvailableChannels(): array
    {
        return ['email', 'sms', 'whatsapp', 'telegram', 'push'];
    }
    
    /**
     * Validate channel name
     *
     * @param string $channel
     * @return bool
     */
    public function isValidChannel(string $channel): bool
    {
        return in_array($channel, $this->getAvailableChannels());
    }
    
    /**
     * Create factory instance with service context
     *
     * @param string $service Service name
     * @param string $language Language code
     * @return static
     */
    public static function for(string $service, string $language = 'en'): static
    {
        return (new static())->forService($service)->inLanguage($language);
    }
}
