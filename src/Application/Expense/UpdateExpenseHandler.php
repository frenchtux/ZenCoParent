<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Expense;

use ZenCoParent\Domain\Expense\ExpenseRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;
use ZenCoParent\Domain\Shared\Exception\ValidationException;

final class UpdateExpenseHandler
{
    public function __construct(
        private ExpenseRepositoryInterface $expenseRepo,
    ) {}

    public function handle(UpdateExpenseCommand $command): ExpenseDTO
    {
        $expense = $this->expenseRepo->findById($command->id)
            ?? throw NotFoundException::forEntity('Expense', $command->id);

        if ($expense->getTenantId() !== $command->tenantId) {
            throw NotFoundException::forEntity('Expense', $command->id);
        }

        if ($command->amount <= 0) {
            throw ValidationException::withErrors(['amount' => 'Amount must be greater than zero']);
        }

        if (trim($command->description) === '') {
            throw ValidationException::withErrors(['description' => 'Description is required']);
        }

        $updated = $expense->withUpdated(
            amount:      $command->amount,
            description: trim($command->description),
            category:    $command->category !== null ? trim($command->category) : null,
            splitRatio:  $command->splitRatio,
            date:        $command->date,
        );

        $this->expenseRepo->update($updated);

        return ExpenseDTO::fromExpense($updated);
    }
}
