<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Auth;

final readonly class LoginResult
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public \ZenCoParent\Application\User\UserDTO $user,
    ) {}
}
