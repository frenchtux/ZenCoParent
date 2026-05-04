<?php
declare(strict_types=1);

namespace ZenCoParent\Application\User;

use ZenCoParent\Domain\User\UserRepositoryInterface;

final class ListUsersHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepo,
    ) {}

    /**
     * @return UserDTO[]
     */
    public function handle(string $tenantId): array
    {
        $users = $this->userRepo->findByTenantId($tenantId);
        return array_map(fn($u) => UserDTO::fromUser($u), $users);
    }
}
