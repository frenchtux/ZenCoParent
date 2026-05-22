<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Child;

final readonly class UpdateChildCommand
{
    public function __construct(
        public string  $id,
        public string  $tenantId,
        public string  $firstName,
        public string  $lastName,
        public ?string $birthdate,
    ) {}
}
