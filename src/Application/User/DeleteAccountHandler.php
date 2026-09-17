<?php
declare(strict_types=1);

namespace ZenCoParent\Application\User;

use ZenCoParent\Domain\Auth\RefreshTokenRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;
use ZenCoParent\Domain\Tenant\TenantRepositoryInterface;
use ZenCoParent\Domain\User\UserRepositoryInterface;

final class DeleteAccountHandler
{
    public function __construct(
        private readonly UserRepositoryInterface         $userRepo,
        private readonly TenantRepositoryInterface       $tenantRepo,
        private readonly RefreshTokenRepositoryInterface $refreshRepo,
    ) {}

    public function handle(string $userId, string $tenantId, string $password): void
    {
        $user = $this->userRepo->findById($userId);
        if ($user === null) {
            throw new NotFoundException('User not found');
        }

        // Verify password before destructive action
        if ($user->getPasswordHash() !== null
            && !password_verify($password, $user->getPasswordHash())) {
            throw new \InvalidArgumentException('Mot de passe incorrect.');
        }

        // Check if this user is the last admin in the tenant
        $allUsers   = $this->userRepo->findByTenantId($tenantId);
        $adminCount = count(array_filter(
            $allUsers,
            fn($u) => $u->getRole()->value === 'admin' && $u->getId() !== $userId,
        ));

        if ($user->getRole()->value === 'admin' && $adminCount === 0) {
            // Last admin leaving: deactivate tenant
            $this->tenantRepo->setActive($tenantId, false);
        }

        // Invalidate all sessions
        $this->refreshRepo->deleteByUserId($userId);

        // Hard-delete user record
        $this->userRepo->delete($userId);
    }
}
