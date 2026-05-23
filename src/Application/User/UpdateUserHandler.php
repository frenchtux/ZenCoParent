<?php
declare(strict_types=1);

namespace ZenCoParent\Application\User;

use ZenCoParent\Domain\User\UserRepositoryInterface;
use ZenCoParent\Domain\User\UserRole;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;

final class UpdateUserHandler
{
    public function __construct(private UserRepositoryInterface $users) {}

    public function handle(UpdateUserCommand $cmd): UserDTO
    {
        $user = $this->users->findById($cmd->id);

        if ($user === null || $user->getTenantId() !== $cmd->tenantId) {
            throw new NotFoundException("Utilisateur introuvable.");
        }

        $updated = $user->withUpdatedProfile(
            firstName: trim($cmd->firstName),
            lastName:  trim($cmd->lastName),
            phone:     $cmd->phone ? trim($cmd->phone) : null,
            address:   $cmd->address ? trim($cmd->address) : null,
        );

        // Admin-only fields: role + active status
        if ($cmd->role !== null || $cmd->isActive !== null) {
            $updated = $updated->withAdminFields(
                role:     $cmd->role !== null ? UserRole::from($cmd->role) : $updated->getRole(),
                isActive: $cmd->isActive ?? $updated->isActive(),
            );
        }

        $this->users->update($updated);

        return UserDTO::fromUser($updated);
    }
}
