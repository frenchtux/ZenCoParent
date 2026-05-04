<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Auth;

final readonly class RefreshTokenCommand
{
    public function __construct(public string $refreshToken) {}
}
