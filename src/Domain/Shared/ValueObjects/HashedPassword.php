<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\Shared\ValueObjects;

final class HashedPassword
{
    private function __construct(private readonly string $hash) {}

    public static function fromPlainText(string $plain): self
    {
        if (strlen($plain) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters long.');
        }

        $hash = password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);

        if ($hash === false) {
            throw new \RuntimeException('Failed to hash password.');
        }

        return new self($hash);
    }

    public static function fromHash(string $hash): self
    {
        return new self($hash);
    }

    public function verify(string $plain): bool
    {
        return password_verify($plain, $this->hash);
    }

    public function toString(): string
    {
        return $this->hash;
    }
}
