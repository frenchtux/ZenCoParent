<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Expense;

use ZenCoParent\Domain\Expense\Expense;
use ZenCoParent\Domain\Expense\ExpenseRepositoryInterface;

final class CreateExpenseHandler
{
    public function __construct(
        private ExpenseRepositoryInterface $expenseRepo,
    ) {}

    public function handle(CreateExpenseCommand $command): Expense
    {
        $expense = Expense::create(
            tenantId:    $command->tenantId,
            paidBy:      $command->paidBy,
            amount:      $command->amount,
            description: $command->description,
            date:        $command->date,
            category:    $command->category,
        );

        $this->expenseRepo->save($expense);

        return $expense;
    }
}
