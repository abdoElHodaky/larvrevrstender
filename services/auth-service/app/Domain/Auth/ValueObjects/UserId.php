<?php

declare(strict_types=1);

namespace App\Domain\Auth\ValueObjects;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class UserId
{
    private function __construct(
        private UuidInterface $value
    ) {}

    public static function generate(): self
    {
        return new self(Uuid::uuid4());
    }

    public static function fromString(string $value): self
    {
        if (!Uuid::isValid($value)) {
            throw new InvalidArgumentException("Invalid UUID format: {$value}");
        }

        return new self(Uuid::fromString($value));
    }

    public function toString(): string
    {
        return $this->value->toString();
    }

    public function equals(self $other): bool
    {
        return $this->value->equals($other->value);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}

