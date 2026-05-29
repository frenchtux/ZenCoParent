<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Expense;

use ZenCoParent\Domain\Expense\ExpenseRepositoryInterface;

final class ListExpensesHandler
{
    public function __construct(
        private ExpenseRepositoryInterface $expenseRepo,
    ) {}

    public function handle(string $tenantId, ?string $from = null, ?string $to = null, ?string $category = null): array
    {
        return $this->expenseRepo->findByTenantId($tenantId, $from, $to, $category);
    }
}
