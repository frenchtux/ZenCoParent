<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Expense;

use ZenCoParent\Domain\Expense\ExpenseRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;

final class DeleteExpenseHandler
{
    public function __construct(
        private ExpenseRepositoryInterface $expenseRepo,
    ) {}

    public function handle(string $expenseId, string $tenantId): void
    {
        if (!$this->expenseRepo->existsForTenant($expenseId, $tenantId)) {
            throw NotFoundException::forEntity('Expense', $expenseId);
        }

        $this->expenseRepo->delete($expenseId);
    }
}
