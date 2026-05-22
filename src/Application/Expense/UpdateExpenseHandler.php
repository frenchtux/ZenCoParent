<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Expense;

use ZenCoParent\Domain\Expense\Expense;
use ZenCoParent\Domain\Expense\ExpenseRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;

final class UpdateExpenseHandler
{
    public function __construct(
        private ExpenseRepositoryInterface $expenseRepo,
    ) {}

    public function handle(UpdateExpenseCommand $command): Expense
    {
        $expense = $this->expenseRepo->findById($command->id);

        if ($expense === null || $expense->getTenantId() !== $command->tenantId) {
            throw new NotFoundException('Dépense introuvable.');
        }

        $updated = $expense->withUpdated(
            amount:      $command->amount,
            description: $command->description,
            date:        $command->date,
            category:    $command->category,
        );

        $this->expenseRepo->update($updated);

        return $updated;
    }
}
