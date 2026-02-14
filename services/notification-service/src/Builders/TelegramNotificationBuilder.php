<?php

namespace NotificationService\Builders;

use Shared\Services\TemplateManager;

/**
 * Telegram Notification Builder
 * 
 * Builder for creating Telegram notifications with Telegram-specific features
 * like inline keyboards, media, and HTML formatting.
 * 
 * @package Shared\Services\Builders
 */
class TelegramNotificationBuilder extends BaseNotificationBuilder
{
    /**
     * Parse mode (HTML, Markdown, MarkdownV2)
     */
    protected string $parseMode = 'HTML';
    
    /**
     * Inline keyboard
     */
    protected array $keyboard = [];
    
    /**
     * Media attachments
     */
    protected array $media = [];
    
    /**
     * Disable web page preview
     */
    protected bool $disableWebPagePreview = false;
    
    /**
     * Constructor
     *
     * @param TemplateManager $templateManager
     */
    public function __construct(TemplateManager $templateManager)
    {
        parent::__construct($templateManager, 'telegram');
    }
    
    /**
     * Set parse mode
     *
     * @param string $parseMode
     * @return static
     */
    public function withParseMode(string $parseMode): static
    {
        $this->parseMode = $parseMode;
        return $this;
    }
    
    /**
     * Add inline keyboard button
     *
     * @param string $text
     * @param string $url
     * @return static
     */
    public function withKeyboardButton(string $text, string $url): static
    {
        $this->keyboard[] = [
            'text' => $text,
            'url' => $url
        ];
        return $this;
    }
    
    /**
     * Disable web page preview
     *
     * @param bool $disable
     * @return static
     */
    public function withoutWebPagePreview(bool $disable = true): static
    {
        $this->disableWebPagePreview = $disable;
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
        
        return $this->sendTelegram(
            $recipient,
            $content,
            [
                'template' => $this->template,
                'data' => $data,
                'parse_mode' => $this->parseMode,
                'keyboard' => $this->keyboard,
                'disable_web_page_preview' => $this->disableWebPagePreview,
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
        // Validate parse mode
        if (!in_array($this->parseMode, ['HTML', 'Markdown', 'MarkdownV2'])) {
            $this->errors[] = "Invalid parse mode: {$this->parseMode}";
        }
        
        // Validate keyboard buttons
        foreach ($this->keyboard as $button) {
            if (!filter_var($button['url'], FILTER_VALIDATE_URL)) {
                $this->errors[] = "Invalid keyboard button URL: {$button['url']}";
            }
        }
    }
}
