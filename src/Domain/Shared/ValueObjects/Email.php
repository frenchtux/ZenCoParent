<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\Shared\ValueObjects;

final class Email
{
    private function __construct(private readonly string $value) {}

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException("Invalid email address: {$value}");
        }

        return new self($normalized);
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
