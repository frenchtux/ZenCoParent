<?php
declare(strict_types=1);

namespace ZenCoParent\Application\User;

use ZenCoParent\Domain\Shared\Exception\NotFoundException;
use ZenCoParent\Domain\Shared\Exception\UnauthorizedException;
use ZenCoParent\Domain\User\UserRepositoryInterface;

final class ChangeCredentialsHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepo,
    ) {}

    public function handle(ChangeCredentialsCommand $command): UserDTO
    {
        $user = $this->userRepo->findById($command->userId)
            ?? throw NotFoundException::forEntity('User', $command->userId);

        if ($user->getTenantId() !== $command->tenantId) {
            throw NotFoundException::forEntity('User', $command->userId);
        }

        if ($user->getPasswordHash() === null ||
            !password_verify($command->currentPassword, $user->getPasswordHash())) {
            throw UnauthorizedException::create('Mot de passe actuel incorrect.');
        }

        $email = strtolower(trim($command->newEmail));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Adresse email invalide.');
        }

        if (strlen($command->newPassword) < 8) {
            throw new \InvalidArgumentException('Le mot de passe doit contenir au moins 8 caractères.');
        }

        // Check email uniqueness if it changed
        if ($email !== $user->getEmail() && $this->userRepo->existsByEmail($command->tenantId, $email)) {
            throw new \InvalidArgumentException('Cette adresse email est déjà utilisée.');
        }

        $updated = $user->withCredentialsChanged($email, password_hash($command->newPassword, PASSWORD_BCRYPT));
        $this->userRepo->update($updated);

        return UserDTO::fromUser($updated);
    }
}
