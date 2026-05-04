<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\User;

interface UserRepositoryInterface
{
    public function findById(string $id): ?User;

    public function findByEmail(string $tenantId, string $email): ?User;

    public function findByTenantId(string $tenantId): array;

    public function save(User $user): void;

    public function update(User $user): void;

    public function delete(string $id): void;

    public function existsByEmail(string $tenantId, string $email): bool;
}
