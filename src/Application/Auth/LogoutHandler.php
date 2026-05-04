<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Auth;

use ZenCoParent\Domain\Auth\RefreshTokenRepositoryInterface;

final class LogoutHandler
{
    public function __construct(
        private RefreshTokenRepositoryInterface $refreshRepo,
    ) {}

    public function handle(string $refreshToken): void
    {
        $hash = hash('sha256', $refreshToken);
        $this->refreshRepo->deleteByHash($hash);
    }
}
