<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\Shared\ValueObjects;

final class Uuid
{
    private function __construct(private readonly string $value) {}

    public static function generate(): self
    {
        return new self(\Ramsey\Uuid\Uuid::uuid4()->toString());
    }

    public static function fromString(string $value): self
    {
        if (!\Ramsey\Uuid\Uuid::isValid($value)) {
            throw new \InvalidArgumentException("Invalid UUID: {$value}");
        }
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
