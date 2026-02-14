<?php

namespace NotificationService\Services\Builders;

use Shared\Services\TemplateManager;
use DateTime;
use DateTimeZone;

/**
 * Scheduled Notification Builder
 * 
 * Builder for creating scheduled notifications with timezone support,
 * recurring schedules, and cancellation capabilities.
 * 
 * @package Shared\Services\Builders
 */
class ScheduledNotificationBuilder extends BaseNotificationBuilder
{
    /**
     * Scheduled send time
     */
    protected ?DateTime $scheduledAt = null;
    
    /**
     * Timezone
     */
    protected ?DateTimeZone $timezone = null;
    
    /**
     * Recurring schedule
     */
    protected ?string $recurring = null;
    
    /**
     * Recurring end date
     */
    protected ?DateTime $recurringUntil = null;
    
    /**
     * Schedule ID for cancellation
     */
    protected ?string $scheduleId = null;
    
    /**
     * Constructor
     *
     * @param TemplateManager $templateManager
     * @param string $channel
     */
    public function __construct(TemplateManager $templateManager, string $channel)
    {
        parent::__construct($templateManager, $channel);
    }
    
    /**
     * Schedule for specific date/time
     *
     * @param DateTime|string $dateTime
     * @return static
     */
    public function scheduleAt($dateTime): static
    {
        if (is_string($dateTime)) {
            $dateTime = new DateTime($dateTime);
        }
        
        $this->scheduledAt = $dateTime;
        return $this;
    }
    
    /**
     * Schedule for delay from now
     *
     * @param int $minutes
     * @return static
     */
    public function scheduleIn(int $minutes): static
    {
        $this->scheduledAt = new DateTime("+{$minutes} minutes");
        return $this;
    }
    
    /**
     * Set timezone
     *
     * @param string|DateTimeZone $timezone
     * @return static
     */
    public function inTimezone($timezone): static
    {
        if (is_string($timezone)) {
            $timezone = new DateTimeZone($timezone);
        }
        
        $this->timezone = $timezone;
        
        // Apply timezone to scheduled time if set
        if ($this->scheduledAt) {
            $this->scheduledAt->setTimezone($timezone);
        }
        
        return $this;
    }
    
    /**
     * Set recurring schedule
     *
     * @param string $pattern (daily, weekly, monthly, yearly)
     * @param DateTime|string|null $until
     * @return static
     */
    public function recurring(string $pattern, $until = null): static
    {
        $this->recurring = $pattern;
        
        if ($until) {
            if (is_string($until)) {
                $until = new DateTime($until);
            }
            $this->recurringUntil = $until;
        }
        
        return $this;
    }
    
    /**
     * Set daily recurring
     *
     * @param DateTime|string|null $until
     * @return static
     */
    public function daily($until = null): static
    {
        return $this->recurring('daily', $until);
    }
    
    /**
     * Set weekly recurring
     *
     * @param DateTime|string|null $until
     * @return static
     */
    public function weekly($until = null): static
    {
        return $this->recurring('weekly', $until);
    }
    
    /**
     * Set monthly recurring
     *
     * @param DateTime|string|null $until
     * @return static
     */
    public function monthly($until = null): static
    {
        return $this->recurring('monthly', $until);
    }
    
    /**
     * Set schedule ID for cancellation
     *
     * @param string $id
     * @return static
     */
    public function withScheduleId(string $id): static
    {
        $this->scheduleId = $id;
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
        // This method is overridden by send() for scheduled processing
        return true;
    }
    
    /**
     * Schedule notification
     *
     * @return bool
     */
    public function send(): bool
    {
        if (!$this->validate()) {
            throw new \Exception('Validation failed: ' . implode(', ', $this->errors));
        }
        
        return $this->scheduleNotification(
            $this->scheduledAt,
            [
                'channel' => $this->channel,
                'recipients' => $this->recipients,
                'template' => $this->template,
                'data' => $this->data,
                'service' => $this->service,
                'language' => $this->language,
                'timezone' => $this->timezone?->getName(),
                'recurring' => $this->recurring,
                'recurring_until' => $this->recurringUntil?->format('Y-m-d H:i:s'),
                'schedule_id' => $this->scheduleId,
                'priority' => $this->priority,
                'tracking' => $this->tracking,
                'metadata' => $this->metadata
            ]
        );
    }
    
    /**
     * Cancel scheduled notification
     *
     * @return bool
     */
    public function cancel(): bool
    {
        if (!$this->scheduleId) {
            throw new \Exception('Schedule ID is required for cancellation');
        }
        
        return $this->cancelNotification($this->scheduleId);
    }
    
    /**
     * Channel-specific validation
     *
     * @return void
     */
    protected function validateChannel(): void
    {
        // Validate scheduled time
        if (!$this->scheduledAt) {
            $this->errors[] = "Scheduled time is required";
        } elseif ($this->scheduledAt <= new DateTime()) {
            $this->errors[] = "Scheduled time must be in the future";
        }
        
        // Validate recurring pattern
        if ($this->recurring && !in_array($this->recurring, ['daily', 'weekly', 'monthly', 'yearly'])) {
            $this->errors[] = "Invalid recurring pattern: {$this->recurring}";
        }
        
        // Validate recurring until date
        if ($this->recurringUntil && $this->recurringUntil <= $this->scheduledAt) {
            $this->errors[] = "Recurring until date must be after scheduled time";
        }
    }
    
    /**
     * Get scheduled time
     *
     * @return DateTime|null
     */
    public function getScheduledAt(): ?DateTime
    {
        return $this->scheduledAt;
    }
    
    /**
     * Get timezone
     *
     * @return DateTimeZone|null
     */
    public function getTimezone(): ?DateTimeZone
    {
        return $this->timezone;
    }
    
    /**
     * Get recurring pattern
     *
     * @return string|null
     */
    public function getRecurring(): ?string
    {
        return $this->recurring;
    }
    
    /**
     * Get schedule ID
     *
     * @return string|null
     */
    public function getScheduleId(): ?string
    {
        return $this->scheduleId;
    }
}
