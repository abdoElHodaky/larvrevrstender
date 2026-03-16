<?php

namespace App\Services\Contracts;

/**
 * Telegram Service Contract
 * 
 * Defines the interface for Telegram bot messaging services
 */
interface TelegramServiceInterface
{
    /**
     * Send Telegram message
     */
    public function sendMessage(string $chatId, string $message, array $options = []): array;

    /**
     * Send message with keyboard
     */
    public function sendMessageWithKeyboard(string $chatId, string $message, array $keyboard): array;

    /**
     * Send photo message
     */
    public function sendPhoto(string $chatId, string $photoPath, string $caption = ''): array;

    /**
     * Send document
     */
    public function sendDocument(string $chatId, string $documentPath, string $caption = ''): array;

    /**
     * Send location
     */
    public function sendLocation(string $chatId, float $latitude, float $longitude): array;

    /**
     * Edit message
     */
    public function editMessage(string $chatId, int $messageId, string $newText): array;

    /**
     * Delete message
     */
    public function deleteMessage(string $chatId, int $messageId): array;

    /**
     * Get chat info
     */
    public function getChatInfo(string $chatId): array;

    /**
     * Get bot info
     */
    public function getBotInfo(): array;

    /**
     * Set webhook
     */
    public function setWebhook(string $webhookUrl): array;

    /**
     * Handle webhook update
     */
    public function handleWebhookUpdate(array $updateData): array;

    /**
     * Get webhook info
     */
    public function getWebhookInfo(): array;

    /**
     * Answer callback query
     */
    public function answerCallbackQuery(string $callbackQueryId, string $text = ''): array;

    /**
     * Get user profile photos
     */
    public function getUserProfilePhotos(int $userId): array;
}
