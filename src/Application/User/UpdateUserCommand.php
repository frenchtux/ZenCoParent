<?php
declare(strict_types=1);

namespace ZenCoParent\Application\User;

final readonly class UpdateUserCommand
{
    public function __construct(
        public string  $id,
        public string  $tenantId,
        public string  $firstName,
        public string  $lastName,
        public ?string $phone,
        public ?string $address,
        public ?string $role,    // only admin can change role
        public ?bool   $isActive, // only admin can toggle
    ) {}
}
