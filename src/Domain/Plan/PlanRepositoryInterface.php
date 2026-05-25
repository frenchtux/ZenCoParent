<?php
declare(strict_types=1);

namespace ZenCoParent\Domain\Plan;

interface PlanRepositoryInterface
{
    /** @return Plan[] */
    public function findAll(): array;

    public function findById(string $id): ?Plan;

    public function findByName(string $name): ?Plan;

    public function save(Plan $plan): void;

    public function update(string $id, array $fields): void;
}
