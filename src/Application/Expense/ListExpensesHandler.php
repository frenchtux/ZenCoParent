<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Expense;

use ZenCoParent\Domain\Expense\ExpenseRepositoryInterface;

final class ListExpensesHandler
{
    public function __construct(
        private ExpenseRepositoryInterface $expenseRepo,
    ) {}

    /** @return ExpenseDTO[] */
    public function handle(
        string  $tenantId,
        ?string $paidBy   = null,
        ?string $category = null,
        ?string $from     = null,
        ?string $to       = null,
    ): array {
        $expenses = $this->expenseRepo->findByTenantId($tenantId, $paidBy, $category, $from, $to);
        return array_map(fn($e) => ExpenseDTO::fromExpense($e), $expenses);
    }
}
