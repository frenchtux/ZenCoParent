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

    public function handle(string $id, string $tenantId): void
    {
        $expense = $this->expenseRepo->findById($id);

        if ($expense === null || $expense->getTenantId() !== $tenantId) {
            throw new NotFoundException('Dépense introuvable.');
        }

        $this->expenseRepo->delete($id);
    }
}
