<?php
declare(strict_types=1);

namespace ZenCoParent\Domain\Photo;

interface PhotoRepositoryInterface
{
    public function findById(string $id): ?Photo;

    /** @return Photo[] ordered by created_at DESC */
    public function findByTenantId(string $tenantId, ?string $childId = null): array;

    public function save(Photo $photo): void;

    public function delete(string $id): void;

    public function existsForTenant(string $id, string $tenantId): bool;
}
