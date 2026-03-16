<?php

namespace App\Services\Contracts;

/**
 * WhatsApp Service Contract
 * 
 * Defines the interface for WhatsApp messaging services
 */
interface WhatsAppServiceInterface
{
    /**
     * Send WhatsApp message
     */
    public function sendMessage(string $phoneNumber, string $message, array $options = []): array;

    /**
     * Send WhatsApp template message
     */
    public function sendTemplateMessage(string $phoneNumber, string $templateName, array $templateData): array;

    /**
     * Send media message
     */
    public function sendMediaMessage(string $phoneNumber, string $mediaUrl, string $mediaType, string $caption = ''): array;

    /**
     * Send location message
     */
    public function sendLocationMessage(string $phoneNumber, float $latitude, float $longitude, string $name = ''): array;

    /**
     * Send contact message
     */
    public function sendContactMessage(string $phoneNumber, array $contactData): array;

    /**
     * Get message status
     */
    public function getMessageStatus(string $messageId): array;

    /**
     * Get WhatsApp templates
     */
    public function getTemplates(): array;

    /**
     * Create WhatsApp template
     */
    public function createTemplate(array $templateData): array;

    /**
     * Update WhatsApp template
     */
    public function updateTemplate(string $templateId, array $templateData): array;

    /**
     * Delete WhatsApp template
     */
    public function deleteTemplate(string $templateId): array;

    /**
     * Validate phone number
     */
    public function validatePhoneNumber(string $phoneNumber): array;

    /**
     * Get account info
     */
    public function getAccountInfo(): array;

    /**
     * Set webhook
     */
    public function setWebhook(string $webhookUrl, string $verifyToken): array;

    /**
     * Handle webhook
     */
    public function handleWebhook(array $webhookData): array;

    /**
     * Get WhatsApp statistics
     */
    public function getWhatsAppStatistics(array $filters = []): array;
}
