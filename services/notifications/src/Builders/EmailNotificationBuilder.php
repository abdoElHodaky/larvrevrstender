<?php

namespace NotificationService\Builders;

use Shared\Services\TemplateManager;

/**
 * Email Notification Builder
 * 
 * Builder for creating email notifications with email-specific features
 * like subject, attachments, and HTML content.
 * 
 * @package Shared\Services\Builders
 */
class EmailNotificationBuilder extends BaseNotificationBuilder
{
    /**
     * Email subject
     */
    protected ?string $subject = null;
    
    /**
     * Email attachments
     */
    protected array $attachments = [];
    
    /**
     * Email headers
     */
    protected array $headers = [];
    
    /**
     * Reply-to address
     */
    protected ?string $replyTo = null;
    
    /**
     * CC recipients
     */
    protected array $cc = [];
    
    /**
     * BCC recipients
     */
    protected array $bcc = [];
    
    /**
     * Constructor
     *
     * @param TemplateManager $templateManager
     */
    public function __construct(TemplateManager $templateManager)
    {
        parent::__construct($templateManager, 'email');
    }
    
    /**
     * Set email subject
     *
     * @param string $subject
     * @return static
     */
    public function withSubject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }
    
    /**
     * Add attachment
     *
     * @param string $path File path
     * @param string|null $name Display name
     * @param string|null $mimeType MIME type
     * @return static
     */
    public function withAttachment(string $path, ?string $name = null, ?string $mimeType = null): static
    {
        $this->attachments[] = [
            'path' => $path,
            'name' => $name ?? basename($path),
            'mime_type' => $mimeType
        ];
        return $this;
    }
    
    /**
     * Add multiple attachments
     *
     * @param array $attachments
     * @return static
     */
    public function withAttachments(array $attachments): static
    {
        foreach ($attachments as $attachment) {
            if (is_string($attachment)) {
                $this->withAttachment($attachment);
            } elseif (is_array($attachment) && isset($attachment['path'])) {
                $this->withAttachment(
                    $attachment['path'],
                    $attachment['name'] ?? null,
                    $attachment['mime_type'] ?? null
                );
            }
        }
        return $this;
    }
    
    /**
     * Set reply-to address
     *
     * @param string $replyTo
     * @return static
     */
    public function withReplyTo(string $replyTo): static
    {
        $this->replyTo = $replyTo;
        return $this;
    }
    
    /**
     * Add CC recipient
     *
     * @param string $email
     * @return static
     */
    public function withCc(string $email): static
    {
        $this->cc[] = $email;
        return $this;
    }
    
    /**
     * Add multiple CC recipients
     *
     * @param array $emails
     * @return static
     */
    public function withCcMany(array $emails): static
    {
        $this->cc = array_merge($this->cc, $emails);
        return $this;
    }
    
    /**
     * Add BCC recipient
     *
     * @param string $email
     * @return static
     */
    public function withBcc(string $email): static
    {
        $this->bcc[] = $email;
        return $this;
    }
    
    /**
     * Add multiple BCC recipients
     *
     * @param array $emails
     * @return static
     */
    public function withBccMany(array $emails): static
    {
        $this->bcc = array_merge($this->bcc, $emails);
        return $this;
    }
    
    /**
     * Add custom header
     *
     * @param string $name
     * @param string $value
     * @return static
     */
    public function withHeader(string $name, string $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }
    
    /**
     * Add multiple headers
     *
     * @param array $headers
     * @return static
     */
    public function withHeaders(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }
    
    /**
     * Set high priority
     *
     * @return static
     */
    public function asHighPriority(): static
    {
        return $this->withPriority('high')
            ->withHeader('X-Priority', '1')
            ->withHeader('X-MSMail-Priority', 'High');
    }
    
    /**
     * Set low priority
     *
     * @return static
     */
    public function asLowPriority(): static
    {
        return $this->withPriority('low')
            ->withHeader('X-Priority', '5')
            ->withHeader('X-MSMail-Priority', 'Low');
    }
    
    /**
     * Mark as newsletter
     *
     * @return static
     */
    public function asNewsletter(): static
    {
        return $this->withHeader('List-Unsubscribe', '<mailto:unsubscribe@example.com>')
            ->withHeader('Precedence', 'bulk');
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
        $subject = $this->buildSubject($data);
        
        return $this->sendEmail(
            $recipient,
            $subject,
            $content,
            [
                'template' => $this->template,
                'data' => $data,
                'attachments' => $this->attachments,
                'headers' => $this->headers,
                'reply_to' => $this->replyTo,
                'cc' => $this->cc,
                'bcc' => $this->bcc,
                'priority' => $this->priority,
                'tracking' => $this->tracking,
                'metadata' => $this->metadata
            ]
        );
    }
    
    /**
     * Build email subject
     *
     * @param array $data
     * @return string
     */
    protected function buildSubject(array $data): string
    {
        if (!$this->subject) {
            // Try to extract subject from template data
            if (isset($data['subject'])) {
                return (string) $data['subject'];
            }
            
            // Generate default subject based on template
            return $this->generateDefaultSubject();
        }
        
        // Process subject as template
        $subject = $this->subject;
        foreach ($data as $key => $value) {
            $subject = str_replace('{{' . $key . '}}', (string) $value, $subject);
        }
        
        return $subject;
    }
    
    /**
     * Generate default subject
     *
     * @return string
     */
    protected function generateDefaultSubject(): string
    {
        if ($this->template) {
            return ucwords(str_replace(['.', '_', '-'], ' ', $this->template));
        }
        
        return 'Notification';
    }
    
    /**
     * Channel-specific validation
     *
     * @return void
     */
    protected function validateChannel(): void
    {
        foreach ($this->recipients as $recipientData) {
            $email = $recipientData['recipient'];
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->errors[] = "Invalid email address: {$email}";
            }
        }
        
        // Validate CC emails
        foreach ($this->cc as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->errors[] = "Invalid CC email address: {$email}";
            }
        }
        
        // Validate BCC emails
        foreach ($this->bcc as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->errors[] = "Invalid BCC email address: {$email}";
            }
        }
        
        // Validate reply-to
        if ($this->replyTo && !filter_var($this->replyTo, FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = "Invalid reply-to email address: {$this->replyTo}";
        }
        
        // Validate attachments
        foreach ($this->attachments as $attachment) {
            if (!file_exists($attachment['path'])) {
                $this->errors[] = "Attachment file not found: {$attachment['path']}";
            }
        }
    }
    
    /**
     * Get email subject
     *
     * @return string|null
     */
    public function getSubject(): ?string
    {
        return $this->subject;
    }
    
    /**
     * Get attachments
     *
     * @return array
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }
    
    /**
     * Get headers
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
    
    /**
     * Get CC recipients
     *
     * @return array
     */
    public function getCc(): array
    {
        return $this->cc;
    }
    
    /**
     * Get BCC recipients
     *
     * @return array
     */
    public function getBcc(): array
    {
        return $this->bcc;
    }
}
