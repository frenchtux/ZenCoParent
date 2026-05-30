<?php
declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence\SQLite;

use ZenCoParent\Domain\Plan\Plan;
use ZenCoParent\Domain\Plan\PlanRepositoryInterface;

/**
 * Community stub — billing plans do not exist in Community mode.
 * All write methods are no-ops; all read methods return null/empty.
 */
final class SQLitePlanRepository implements PlanRepositoryInterface
{
    public function findAll(): array
    {
        return [];
    }

    public function findById(string $id): ?Plan
    {
        return null;
    }

    public function findByName(string $name): ?Plan
    {
        return null;
    }

    public function save(Plan $plan): void {}

    public function update(string $id, array $fields): void {}
}
