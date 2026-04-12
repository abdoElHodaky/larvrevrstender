<?php

namespace App\Services\Contracts;

/**
 * Signal Service Contract
 * 
 * Defines the interface for Signal messaging services
 */
interface SignalServiceInterface
{
    /**
     * Send Signal message
     */
    public function sendSignalMessage(string $phoneNumber, string $message, array $options = []): array;

    /**
     * Send Signal message to group
     */
    public function sendGroupMessage(string $groupId, string $message, array $options = []): array;

    /**
     * Send Signal message with attachment
     */
    public function sendMessageWithAttachment(string $phoneNumber, string $message, array $attachments): array;

    /**
     * Create Signal group
     */
    public function createGroup(string $groupName, array $members): array;

    /**
     * Add member to group
     */
    public function addGroupMember(string $groupId, string $phoneNumber): array;

    /**
     * Remove member from group
     */
    public function removeGroupMember(string $groupId, string $phoneNumber): array;

    /**
     * Get group members
     */
    public function getGroupMembers(string $groupId): array;

    /**
     * Get message delivery status
     */
    public function getDeliveryStatus(string $messageId): array;

    /**
     * Get Signal message history
     */
    public function getMessageHistory(string $phoneNumber, array $filters = []): array;

    /**
     * Validate Signal phone number
     */
    public function validateSignalNumber(string $phoneNumber): array;

    /**
     * Get Signal account info
     */
    public function getAccountInfo(): array;

    /**
     * Register Signal webhook
     */
    public function registerWebhook(string $webhookUrl, array $events): array;

    /**
     * Handle Signal webhook
     */
    public function handleWebhook(array $webhookData): array;
}
