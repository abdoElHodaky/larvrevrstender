<?php

namespace Workflow;

/**
 * Activity Options stub for workflow compatibility
 * 
 * This is a placeholder implementation to resolve missing class errors.
 * TODO: Replace with actual implementation when proper workflow library is integrated.
 */
class ActivityOptions
{
    protected array $options = [];

    public static function new(): self
    {
        return new self();
    }

    public function withRetryOptions(RetryOptions $retryOptions): self
    {
        $this->options['retry_options'] = $retryOptions;
        return $this;
    }

    public function withStartToCloseTimeout(int $timeout): self
    {
        $this->options['start_to_close_timeout'] = $timeout;
        return $this;
    }

    public function withScheduleToCloseTimeout(int $timeout): self
    {
        $this->options['schedule_to_close_timeout'] = $timeout;
        return $this;
    }

    public function withScheduleToStartTimeout(int $timeout): self
    {
        $this->options['schedule_to_start_timeout'] = $timeout;
        return $this;
    }

    public function withHeartbeatTimeout(int $timeout): self
    {
        $this->options['heartbeat_timeout'] = $timeout;
        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
