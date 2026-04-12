<?php

namespace App\Services\Contracts;

/**
 * SMS Service Contract
 * 
 * Defines the interface for SMS notification services
 */
interface SmsServiceInterface
{
    /**
     * Send SMS notification
     */
    public function sendSms(string $phoneNumber, string $message, array $options = []): array;

    /**
     * Send bulk SMS
     */
    public function sendBulkSms(array $phoneNumbers, string $message, array $options = []): array;

    /**
     * Send templated SMS
     */
    public function sendTemplatedSms(string $phoneNumber, string $templateId, array $templateData = []): array;

    /**
     * Get SMS templates
     */
    public function getSmsTemplates(): array;

    /**
     * Create SMS template
     */
    public function createSmsTemplate(array $templateData): array;

    /**
     * Update SMS template
     */
    public function updateSmsTemplate(string $templateId, array $templateData): array;

    /**
     * Delete SMS template
     */
    public function deleteSmsTemplate(string $templateId): array;

    /**
     * Get SMS delivery status
     */
    public function getDeliveryStatus(string $messageId): array;

    /**
     * Get SMS statistics
     */
    public function getSmsStatistics(array $filters = []): array;

    /**
     * Validate phone number
     */
    public function validatePhoneNumber(string $phoneNumber): array;

    /**
     * Get SMS preferences
     */
    public function getSmsPreferences(string $phoneNumber): array;

    /**
     * Update SMS preferences
     */
    public function updateSmsPreferences(string $phoneNumber, array $preferences): array;

    /**
     * Handle SMS delivery failure
     */
    public function handleDeliveryFailure(string $messageId, string $reason): array;
}
