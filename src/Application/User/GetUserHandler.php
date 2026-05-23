<?php
declare(strict_types=1);

namespace ZenCoParent\Application\User;

use ZenCoParent\Domain\User\UserRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;

final class GetUserHandler
{
    public function __construct(private UserRepositoryInterface $users) {}

    public function handle(string $id, string $tenantId): UserDTO
    {
        $user = $this->users->findById($id);

        if ($user === null || $user->getTenantId() !== $tenantId) {
            throw new NotFoundException("Utilisateur introuvable.");
        }

        return UserDTO::fromUser($user);
    }
}
