<?php
declare(strict_types=1);

namespace ZenCoParent\Application\User;

final readonly class CreateUserCommand
{
    public function __construct(
        public string  $tenantId,
        public string  $email,
        public string  $password,
        public string  $firstName,
        public string  $lastName,
        public string  $role    = 'parent',
        public ?string $phone   = null,
        public ?string $address = null,
    ) {}
}
