<?php

declare(strict_types=1);

namespace ZenCoParent\Application\Invitation;

final readonly class CreateInvitationCommand
{
    public function __construct(
        public string $tenantId,
        public string $invitedBy,
        public string $email,
        public string $role,
    ) {}
}
