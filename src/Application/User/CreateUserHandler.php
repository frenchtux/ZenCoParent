<?php
declare(strict_types=1);

namespace ZenCoParent\Application\User;

use ZenCoParent\Domain\Shared\Exception\ValidationException;
use ZenCoParent\Domain\User\Exception\UserAlreadyExistsException;
use ZenCoParent\Domain\User\User;
use ZenCoParent\Domain\User\UserRepositoryInterface;
use ZenCoParent\Domain\User\UserRole;

final class CreateUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepo,
    ) {}

    public function handle(CreateUserCommand $command): UserDTO
    {
        // 1. Validate email format
        if (!filter_var($command->email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withErrors(['email' => 'Invalid email address.']);
        }

        // 2. Validate password length >= 8
        if (strlen($command->password) < 8) {
            throw ValidationException::withErrors(['password' => 'Password must be at least 8 characters.']);
        }

        // 3. Check existsByEmail — throw UserAlreadyExistsException if exists
        if ($this->userRepo->existsByEmail($command->tenantId, $command->email)) {
            throw UserAlreadyExistsException::forEmail($command->email);
        }

        // 4. Hash password
        $passwordHash = password_hash($command->password, PASSWORD_BCRYPT, ['cost' => 12]);

        // 5. Resolve role
        $role = UserRole::tryFrom($command->role) ?? UserRole::Parent;

        // 6. Create User entity and save
        $user = User::create(
            tenantId:     $command->tenantId,
            email:        $command->email,
            passwordHash: $passwordHash,
            firstName:    $command->firstName,
            lastName:     $command->lastName,
            role:         $role,
        );

        $this->userRepo->save($user);

        return UserDTO::fromUser($user);
    }
}
