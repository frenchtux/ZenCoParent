<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\User\Exception;

use ZenCoParent\Domain\Shared\Exception\DomainException;

final class UserAlreadyExistsException extends DomainException
{
    public static function forEmail(string $email): self
    {
        return new self("A user with email '{$email}' already exists.");
    }
}
