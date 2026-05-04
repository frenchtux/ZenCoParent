<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\Tenant;

interface TenantRepositoryInterface
{
    public function findById(string $id): ?Tenant;

    public function findBySlug(string $slug): ?Tenant;

    public function save(Tenant $tenant): void;
}
