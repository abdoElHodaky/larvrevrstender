<?php

namespace App\Services\Contracts;

/**
 * Email Service Contract
 * 
 * Defines the interface for email notification services
 */
interface EmailServiceInterface
{
    /**
     * Send email notification
     */
    public function sendEmail(string $to, string $subject, string $body, array $options = []): array;

    /**
     * Send bulk emails
     */
    public function sendBulkEmails(array $recipients, string $subject, string $body, array $options = []): array;

    /**
     * Send templated email
     */
    public function sendTemplatedEmail(string $to, string $templateId, array $templateData = []): array;

    /**
     * Get email templates
     */
    public function getEmailTemplates(): array;

    /**
     * Create email template
     */
    public function createEmailTemplate(array $templateData): array;

    /**
     * Update email template
     */
    public function updateEmailTemplate(string $templateId, array $templateData): array;

    /**
     * Delete email template
     */
    public function deleteEmailTemplate(string $templateId): array;

    /**
     * Get email delivery status
     */
    public function getDeliveryStatus(string $messageId): array;

    /**
     * Get email statistics
     */
    public function getEmailStatistics(array $filters = []): array;

    /**
     * Validate email address
     */
    public function validateEmailAddress(string $email): array;

    /**
     * Get bounce list
     */
    public function getBounceList(): array;

    /**
     * Handle email bounce
     */
    public function handleEmailBounce(string $email, string $reason): array;

    /**
     * Get email preferences
     */
    public function getEmailPreferences(string $email): array;

    /**
     * Update email preferences
     */
    public function updateEmailPreferences(string $email, array $preferences): array;
}
