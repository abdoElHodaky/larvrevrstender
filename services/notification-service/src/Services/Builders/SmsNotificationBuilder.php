<?php

namespace NotificationService\Services\Builders;

use Shared\Services\TemplateManager;

/**
 * SMS Notification Builder
 * 
 * Builder for creating SMS notifications with SMS-specific features
 * like character limits, provider selection, and delivery reports.
 * 
 * @package Shared\Services\Builders
 */
class SmsNotificationBuilder extends BaseNotificationBuilder
{
    /**
     * SMS provider
     */
    protected ?string $provider = null;
    
    /**
     * Sender ID
     */
    protected ?string $senderId = null;
    
    /**
     * Maximum message length
     */
    protected int $maxLength = 160;
    
    /**
     * Enable delivery reports
     */
    protected bool $deliveryReports = false;
    
    /**
     * Message encoding
     */
    protected string $encoding = 'GSM7';
    
    /**
     * Constructor
     *
     * @param TemplateManager $templateManager
     */
    public function __construct(TemplateManager $templateManager)
    {
        parent::__construct($templateManager, 'sms');
    }
    
    /**
     * Set SMS provider
     *
     * @param string $provider (unifonic, msegat, oursms, infobip)
     * @return static
     */
    public function withProvider(string $provider): static
    {
        $this->provider = $provider;
        return $this;
    }
    
    /**
     * Set sender ID
     *
     * @param string $senderId
     * @return static
     */
    public function withSenderId(string $senderId): static
    {
        $this->senderId = $senderId;
        return $this;
    }
    
    /**
     * Set maximum message length
     *
     * @param int $maxLength
     * @return static
     */
    public function withMaxLength(int $maxLength): static
    {
        $this->maxLength = $maxLength;
        return $this;
    }
    
    /**
     * Enable delivery reports
     *
     * @param bool $enabled
     * @return static
     */
    public function withDeliveryReports(bool $enabled = true): static
    {
        $this->deliveryReports = $enabled;
        return $this;
    }
    
    /**
     * Set message encoding
     *
     * @param string $encoding (GSM7, UCS2)
     * @return static
     */
    public function withEncoding(string $encoding): static
    {
        $this->encoding = $encoding;
        return $this;
    }
    
    /**
     * Use Unifonic provider
     *
     * @return static
     */
    public function viaUnifonic(): static
    {
        return $this->withProvider('unifonic');
    }
    
    /**
     * Use Msegat provider
     *
     * @return static
     */
    public function viaMsegat(): static
    {
        return $this->withProvider('msegat');
    }
    
    /**
     * Use OurSMS provider
     *
     * @return static
     */
    public function viaOurSms(): static
    {
        return $this->withProvider('oursms');
    }
    
    /**
     * Use Infobip provider
     *
     * @return static
     */
    public function viaInfobip(): static
    {
        return $this->withProvider('infobip');
    }
    
    /**
     * Set as promotional message
     *
     * @return static
     */
    public function asPromotional(): static
    {
        return $this->withMeta('message_type', 'promotional')
            ->withPriority('low');
    }
    
    /**
     * Set as transactional message
     *
     * @return static
     */
    public function asTransactional(): static
    {
        return $this->withMeta('message_type', 'transactional')
            ->withPriority('high')
            ->withDeliveryReports(true);
    }
    
    /**
     * Set as OTP message
     *
     * @return static
     */
    public function asOtp(): static
    {
        return $this->withMeta('message_type', 'otp')
            ->withPriority('urgent')
            ->withDeliveryReports(true)
            ->withMaxLength(70); // Shorter for OTP
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
        
        // Truncate content if it exceeds max length
        if (strlen($content) > $this->maxLength) {
            $content = substr($content, 0, $this->maxLength - 3) . '...';
        }
        
        return $this->sendSms(
            $recipient,
            $content,
            [
                'template' => $this->template,
                'data' => $data,
                'provider' => $this->provider,
                'sender_id' => $this->senderId,
                'delivery_reports' => $this->deliveryReports,
                'encoding' => $this->encoding,
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
            
            // Basic phone number validation
            if (!preg_match('/^\+?[1-9]\d{1,14}$/', $phone)) {
                $this->errors[] = "Invalid phone number format: {$phone}";
            }
        }
        
        // Validate provider
        if ($this->provider && !in_array($this->provider, ['unifonic', 'msegat', 'oursms', 'infobip'])) {
            $this->errors[] = "Invalid SMS provider: {$this->provider}";
        }
        
        // Validate sender ID
        if ($this->senderId && strlen($this->senderId) > 11) {
            $this->errors[] = "Sender ID too long (max 11 characters): {$this->senderId}";
        }
        
        // Validate encoding
        if (!in_array($this->encoding, ['GSM7', 'UCS2'])) {
            $this->errors[] = "Invalid encoding: {$this->encoding}";
        }
        
        // Validate max length
        if ($this->maxLength < 1 || $this->maxLength > 1600) {
            $this->errors[] = "Invalid max length (1-1600): {$this->maxLength}";
        }
    }
    
    /**
     * Estimate message parts
     *
     * @param string $content
     * @return int
     */
    public function estimateMessageParts(string $content): int
    {
        $length = strlen($content);
        
        if ($this->encoding === 'UCS2') {
            // Unicode messages
            if ($length <= 70) return 1;
            return ceil($length / 67);
        } else {
            // GSM 7-bit encoding
            if ($length <= 160) return 1;
            return ceil($length / 153);
        }
    }
    
    /**
     * Get estimated cost (placeholder - implement based on provider rates)
     *
     * @param string $content
     * @return float
     */
    public function estimateCost(string $content): float
    {
        $parts = $this->estimateMessageParts($content);
        
        // Base cost per part (this should come from provider configuration)
        $costPerPart = match ($this->provider) {
            'unifonic' => 0.05,
            'msegat' => 0.04,
            'oursms' => 0.03,
            'infobip' => 0.06,
            default => 0.05
        };
        
        return $parts * $costPerPart;
    }
    
    /**
     * Get SMS provider
     *
     * @return string|null
     */
    public function getProvider(): ?string
    {
        return $this->provider;
    }
    
    /**
     * Get sender ID
     *
     * @return string|null
     */
    public function getSenderId(): ?string
    {
        return $this->senderId;
    }
    
    /**
     * Get max length
     *
     * @return int
     */
    public function getMaxLength(): int
    {
        return $this->maxLength;
    }
    
    /**
     * Check if delivery reports are enabled
     *
     * @return bool
     */
    public function hasDeliveryReports(): bool
    {
        return $this->deliveryReports;
    }
    
    /**
     * Get encoding
     *
     * @return string
     */
    public function getEncoding(): string
    {
        return $this->encoding;
    }
}
