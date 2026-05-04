<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Child;

final readonly class CreateChildCommand
{
    public function __construct(
        public string  $tenantId,
        public string  $firstName,
        public string  $lastName,
        public ?string $birthdate,
        public string  $createdBy,
    ) {}
}
