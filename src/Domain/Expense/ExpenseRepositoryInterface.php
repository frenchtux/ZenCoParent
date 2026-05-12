<?php
declare(strict_types=1);

namespace ZenCoParent\Domain\Expense;

interface ExpenseRepositoryInterface
{
    public function findById(string $id): ?Expense;

    /** @return Expense[] ordered by date DESC */
    public function findByTenantId(
        string  $tenantId,
        ?string $paidBy   = null,
        ?string $category = null,
        ?string $from     = null,
        ?string $to       = null,
    ): array;

    public function save(Expense $expense): void;

    public function update(Expense $expense): void;

    public function delete(string $id): void;

    public function existsForTenant(string $id, string $tenantId): bool;
}
