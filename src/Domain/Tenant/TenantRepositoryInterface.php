<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\Tenant;

interface TenantRepositoryInterface
{
    public function findById(string $id): ?Tenant;

    public function findBySlug(string $slug): ?Tenant;

    /** @return Tenant[] */
    public function findAll(int $limit = 50, int $offset = 0): array;

    /** Total number of tenants (families). */
    public function countAll(): int;

    public function save(Tenant $tenant): void;

    public function updateModulesOverride(string $id, ?array $modules): void;

    public function setActive(string $id, bool $active): void;
}
