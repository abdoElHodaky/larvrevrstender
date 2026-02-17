<?php

namespace Shared\Idempotency;

use Exception;

/**
 * Idempotency Exception
 * 
 * Thrown when idempotency violations occur, such as:
 * - Attempting to execute an operation that's already in progress
 * - Attempting to retry a recently failed operation too soon
 */
class IdempotencyException extends Exception
{
    private array $context;

    public function __construct(string $message, array $context = [], int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get additional context about the idempotency violation
     *
     * @return array Context data
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get the idempotency key that caused the violation
     *
     * @return string|null Idempotency key
     */
    public function getIdempotencyKey(): ?string
    {
        return $this->context['idempotency_key'] ?? null;
    }
}
