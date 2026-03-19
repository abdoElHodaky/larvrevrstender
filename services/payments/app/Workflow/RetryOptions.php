<?php

namespace Workflow;

/**
 * Retry Options stub for workflow compatibility
 * 
 * This is a placeholder implementation to resolve missing class errors.
 * TODO: Replace with actual implementation when proper workflow library is integrated.
 */
class RetryOptions
{
    protected array $options = [];

    public static function new(): self
    {
        return new self();
    }

    public function withMaximumAttempts(int $attempts): self
    {
        $this->options['maximum_attempts'] = $attempts;
        return $this;
    }

    public function withInitialInterval(int $interval): self
    {
        $this->options['initial_interval'] = $interval;
        return $this;
    }

    public function withMaximumInterval(int $interval): self
    {
        $this->options['maximum_interval'] = $interval;
        return $this;
    }

    public function withBackoffCoefficient(float $coefficient): self
    {
        $this->options['backoff_coefficient'] = $coefficient;
        return $this;
    }

    public function withMaximumIntervalCoefficient(float $coefficient): self
    {
        $this->options['maximum_interval_coefficient'] = $coefficient;
        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
