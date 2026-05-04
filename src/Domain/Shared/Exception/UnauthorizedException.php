<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\Shared\Exception;

final class UnauthorizedException extends DomainException
{
    public static function create(string $message = 'Unauthorized'): self
    {
        return new self($message);
    }
}
