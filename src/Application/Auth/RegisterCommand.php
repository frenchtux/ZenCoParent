<?php

declare(strict_types=1);

namespace ZenCoParent\Application\Auth;

final readonly class RegisterCommand
{
    public function __construct(
        public string $familyName,
        public string $email,
        public string $password,
        public string $firstName,
        public string $lastName,
    ) {}
}
