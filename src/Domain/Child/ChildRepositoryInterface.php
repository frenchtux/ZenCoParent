<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\Child;

interface ChildRepositoryInterface
{
    public function findById(string $id): ?Child;

    public function findByTenantId(string $tenantId): array;

    public function save(Child $child): void;

    public function update(Child $child): void;

    public function delete(string $id): void;
}
