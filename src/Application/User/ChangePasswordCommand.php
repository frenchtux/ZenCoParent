<?php
declare(strict_types=1);

namespace ZenCoParent\Application\User;

final readonly class ChangePasswordCommand
{
    public function __construct(
        public string  $id,
        public string  $tenantId,
        public ?string $currentPassword, // null when an admin resets another user's password
        public string  $newPassword,
        public bool    $isAdminReset,
    ) {}
}
