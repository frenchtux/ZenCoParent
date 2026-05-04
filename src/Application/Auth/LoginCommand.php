<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Auth;

final readonly class LoginCommand
{
    public function __construct(
        public string $email,
        public string $password,
        public string $tenantSlug,
    ) {}
}
