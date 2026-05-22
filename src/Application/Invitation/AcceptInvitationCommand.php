<?php

declare(strict_types=1);

namespace ZenCoParent\Application\Invitation;

final readonly class AcceptInvitationCommand
{
    public function __construct(
        public string $token,
        public string $firstName,
        public string $lastName,
        public string $password,
    ) {}
}
