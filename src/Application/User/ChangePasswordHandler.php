<?php
declare(strict_types=1);

namespace ZenCoParent\Application\User;

use ZenCoParent\Domain\User\UserRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;
use ZenCoParent\Domain\Shared\Exception\UnauthorizedException;

final class ChangePasswordHandler
{
    public function __construct(private UserRepositoryInterface $users) {}

    public function handle(ChangePasswordCommand $cmd): void
    {
        $user = $this->users->findById($cmd->id);

        if ($user === null || $user->getTenantId() !== $cmd->tenantId) {
            throw new NotFoundException("Utilisateur introuvable.");
        }

        // Non-admin users must provide their current password
        if (!$cmd->isAdminReset) {
            if ($cmd->currentPassword === null) {
                throw new UnauthorizedException("Mot de passe actuel requis.");
            }
            if (!password_verify($cmd->currentPassword, (string) $user->getPasswordHash())) {
                throw new UnauthorizedException("Mot de passe actuel incorrect.");
            }
        }

        if (strlen($cmd->newPassword) < 8) {
            throw new \InvalidArgumentException("Le nouveau mot de passe doit contenir au moins 8 caractères.");
        }

        $updated = $user->withNewPassword(password_hash($cmd->newPassword, PASSWORD_BCRYPT));
        $this->users->update($updated);
    }
}
