<?php
declare(strict_types=1);

namespace ZenCoParent\Application\User;

final readonly class ChangeCredentialsCommand
{
    public function __construct(
        public string $userId,
        public string $tenantId,
        public string $newEmail,
        public string $newPassword,
        public string $currentPassword,
    ) {}
}
