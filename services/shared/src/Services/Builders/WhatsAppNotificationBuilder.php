<?php

namespace Shared\Services\Builders;

use Shared\Services\TemplateManager;

/**
 * WhatsApp Notification Builder
 * 
 * Builder for creating WhatsApp notifications with WhatsApp-specific features
 * like media attachments, interactive buttons, and business templates.
 * 
 * @package Shared\Services\Builders
 */
class WhatsAppNotificationBuilder extends BaseNotificationBuilder
{
    /**
     * WhatsApp provider
     */
    protected ?string $provider = null;
    
    /**
     * Media attachments
     */
    protected array $media = [];
    
    /**
     * Interactive buttons
     */
    protected array $buttons = [];
    
    /**
     * Business template ID
     */
    protected ?string $businessTemplate = null;
    
    /**
     * Template parameters
     */
    protected array $templateParams = [];
    
    /**
     * Constructor
     *
     * @param TemplateManager $templateManager
     */
    public function __construct(TemplateManager $templateManager)
    {
        parent::__construct($templateManager, 'whatsapp');
    }
    
    /**
     * Set WhatsApp provider
     *
     * @param string $provider
     * @return static
     */
    public function withProvider(string $provider): static
    {
        $this->provider = $provider;
        return $this;
    }
    
    /**
     * Add media attachment
     *
     * @param string $type (image, video, audio, document)
     * @param string $url Media URL
     * @param string|null $caption Caption text
     * @return static
     */
    public function withMedia(string $type, string $url, ?string $caption = null): static
    {
        $this->media[] = [
            'type' => $type,
            'url' => $url,
            'caption' => $caption
        ];
        return $this;
    }
    
    /**
     * Add image attachment
     *
     * @param string $url
     * @param string|null $caption
     * @return static
     */
    public function withImage(string $url, ?string $caption = null): static
    {
        return $this->withMedia('image', $url, $caption);
    }
    
    /**
     * Add video attachment
     *
     * @param string $url
     * @param string|null $caption
     * @return static
     */
    public function withVideo(string $url, ?string $caption = null): static
    {
        return $this->withMedia('video', $url, $caption);
    }
    
    /**
     * Add document attachment
     *
     * @param string $url
     * @param string|null $caption
     * @return static
     */
    public function withDocument(string $url, ?string $caption = null): static
    {
        return $this->withMedia('document', $url, $caption);
    }
    
    /**
     * Add interactive button
     *
     * @param string $text Button text
     * @param string $action Action type (url, phone, quick_reply)
     * @param string $value Action value
     * @return static
     */
    public function withButton(string $text, string $action, string $value): static
    {
        $this->buttons[] = [
            'text' => $text,
            'action' => $action,
            'value' => $value
        ];
        return $this;
    }
    
    /**
     * Add URL button
     *
     * @param string $text
     * @param string $url
     * @return static
     */
    public function withUrlButton(string $text, string $url): static
    {
        return $this->withButton($text, 'url', $url);
    }
    
    /**
     * Add phone button
     *
     * @param string $text
     * @param string $phone
     * @return static
     */
    public function withPhoneButton(string $text, string $phone): static
    {
        return $this->withButton($text, 'phone', $phone);
    }
    
    /**
     * Add quick reply button
     *
     * @param string $text
     * @param string $payload
     * @return static
     */
    public function withQuickReply(string $text, string $payload): static
    {
        return $this->withButton($text, 'quick_reply', $payload);
    }
    
    /**
     * Use business template
     *
     * @param string $templateId
     * @param array $params
     * @return static
     */
    public function withBusinessTemplate(string $templateId, array $params = []): static
    {
        $this->businessTemplate = $templateId;
        $this->templateParams = $params;
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
        
        return $this->sendWhatsApp(
            $recipient,
            $content,
            [
                'template' => $this->template,
                'data' => $data,
                'provider' => $this->provider,
                'media' => $this->media,
                'buttons' => $this->buttons,
                'business_template' => $this->businessTemplate,
                'template_params' => $this->templateParams,
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
        foreach ($this->recipients as $recipientData) {
            $phone = $recipientData['recipient'];
            
            // WhatsApp phone number validation
            if (!preg_match('/^\+?[1-9]\d{1,14}$/', $phone)) {
                $this->errors[] = "Invalid WhatsApp phone number format: {$phone}";
            }
        }
        
        // Validate media URLs
        foreach ($this->media as $media) {
            if (!filter_var($media['url'], FILTER_VALIDATE_URL)) {
                $this->errors[] = "Invalid media URL: {$media['url']}";
            }
        }
        
        // Validate button limits
        if (count($this->buttons) > 3) {
            $this->errors[] = "Maximum 3 buttons allowed";
        }
        
        // Validate button actions
        foreach ($this->buttons as $button) {
            if (!in_array($button['action'], ['url', 'phone', 'quick_reply'])) {
                $this->errors[] = "Invalid button action: {$button['action']}";
            }
        }
    }
}
